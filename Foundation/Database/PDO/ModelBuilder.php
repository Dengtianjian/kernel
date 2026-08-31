<?php

namespace kernel\Foundation\Database\PDO;

/**
 * Model 查询构建器
 *
 * 承担 Model 的全部查询职责，是 `Model::scopedBuilder()` / `Model::builder()` 的返回值。
 * 对应 Laravel 的 `Illuminate\Database\Eloquent\Builder`。
 *
 * ## 为什么需要这一层
 *
 * 早期实现中 `Model::__call()` 直接把方法转发给 Model 持有的**同一个** Query 实例，
 * 而 Query 只在写操作后 reset，导致查询条件跨调用累积：
 *
 * ```php
 * $model->where('key', $k)->exists();   // 条件留在 Query 上
 * $model->where('key', $k)->delete();   // 变成 AND `key` = ? AND `key` = ?
 * ```
 *
 * ModelBuilder 的生命周期**严格等于一次查询链**：每次从 Model 发起查询都新建一个
 * Builder（内部持有全新 Query），链式方法返回 Builder 自身，链结束后 Builder 即被丢弃。
 * 因此任何两次查询之间不可能共享状态。
 *
 * ## 职责划分
 *
 * - `Model`      ：Active Record —— 属性读写、save/delete/restore、关联定义、类型转换
 * - `ModelBuilder`：查询 —— where/orderBy/get/first 等，以及结果 hydrate 与 eager loading
 *
 * ## 链式语义
 *
 * ```php
 * UserModel::where('status', 1)->orderBy('id')->get();  // Builder 链，互不干扰
 * UserModel::where('a', 1)->get();
 * UserModel::where('b', 2)->get();                       // 不受上一行影响
 * ```
 *
 * 注意：从 Model 实例**重新发起**链式调用会开启新的 Builder，此前未终结的链会被丢弃：
 *
 * ```php
 * $user->where('a', 1);   // 条件被丢弃（未接续链式，也未执行）
 * $user->get();           // 查全表，不含 a=1
 * ```
 *
 * @see Model::scopedBuilder() 创建入口（含全局作用域）
 */
class ModelBuilder
{
  /**
   * 需要把结果行转换为 Model 实例的终端方法
   *
   * 仅在声明了 with() 预加载时转换；否则保持 Query 原始返回（关联数组），
   * 与既有行为一致。
   */
  private const HYDRATE_METHODS = ['get', 'first', 'paginate'];

  /**
   * 方法别名映射
   *
   * 用于在构建器层提供同义名，而无需在 Query 里复制一份方法体。
   * 映射在转发前完成，因此后续的作用域应用、结果 hydrate 都按目标方法处理。
   *
   * - `all()` → `get()`：语义完全一致，仅命名更贴近 SQL 习惯
   */
  private const METHOD_ALIASES = [
    'all' => 'get',
  ];

  /**
   * 会真正执行 SQL 的方法
   *
   * 全局作用域（软删除过滤）在这些方法调用前才应用，
   * 因此 withTrashed()/onlyTrashed() 在链中任意位置声明都有效。
   */
  private const EXECUTE_METHODS = [
    'get',
    'first',
    'value',
    'pluck',
    'paginate',
    'count',
    'exists',
    'notExists',
    'max',
    'min',
    'avg',
    'sum',
    'insert',
    'insertGetId',
    'update',
    'delete',
    'cursor',
    'chunk',
    'chunkById',
  ];

  /**
   * 原型 Model 实例
   *
   * 仅用于：读取表名/作用域配置、把结果行 hydrate 成 Model、执行 eager loading。
   * Builder 不会修改它的状态。
   *
   * @var Model
   */
  protected Model $model;

  /**
   * 本次查询链专属的 Query 实例
   *
   * 由 Model::query() 新建，不与其他 Builder 共享。
   *
   * @var Query
   */
  protected Query $query;

  /**
   * 待预加载的关联关系名列表
   *
   * @var array<string>
   */
  protected array $eagerLoads = [];

  /**
   * 软删除作用域模式，取自 Model 的当前设置
   *
   * @var int Model::TRASHED_*
   */
  protected int $trashedScope;

  /**
   * 全局作用域是否已应用
   *
   * 延迟到首个执行方法前应用，保证 withTrashed()/onlyTrashed() 可覆盖。
   *
   * @var bool
   */
  private bool $scopesApplied = false;

  /**
   * 构造查询构建器
   *
   * @param Model $model       原型 Model 实例
   * @param array $eagerLoads  预加载的关联关系名列表
   * @param int   $trashedScope 软删除作用域模式
   */
  public function __construct(Model $model, array $eagerLoads = [], int $trashedScope = Model::TRASHED_EXCLUDE)
  {
    $this->model        = $model;
    $this->eagerLoads   = $eagerLoads;
    $this->trashedScope = $trashedScope;
    $this->query        = $model->query();
  }

  /**
   * 方法代理：转发给底层 Query
   *
   * - Query 链式方法（返回 Query 自身）→ 返回 $this，保持 Builder 链
   * - 执行方法 → 先应用全局作用域，再执行
   * - 声明了 with() 的终端方法 → 结果行转 Model 实例并批量预加载
   *
   * @param string $method     方法名
   * @param array  $parameters 参数
   * @return mixed
   */
  public function __call($method, $parameters)
  {
    // 先解析别名（如 all() → get()），后续逻辑统一按目标方法处理
    $method = self::METHOD_ALIASES[$method] ?? $method;

    if (!$this->scopesApplied && in_array($method, self::EXECUTE_METHODS, true)) {
      $this->applyScopes();
    }

    $result = $this->query->$method(...$parameters);

    if ($result === $this->query) {
      return $this;
    }

    if (!empty($this->eagerLoads) && in_array($method, self::HYDRATE_METHODS, true)) {
      return $this->hydrateResult($method, $result);
    }

    return $result;
  }

  /**
   * 设置需要预加载的关联关系
   *
   * @param string ...$relations 关系方法名列表
   * @return $this
   *
   * @example
   * UserModel::with('profile', 'posts')->where('status', 1)->get();
   */
  public function with(string ...$relations): static
  {
    $this->eagerLoads = array_values(array_unique(array_merge($this->eagerLoads, $relations)));

    return $this;
  }

  /**
   * 查询范围：包含已软删除的记录
   *
   * 不加任何 deleted_at 条件。需在终端方法前调用。
   *
   * @return $this
   */
  public function withTrashed(): static
  {
    $this->trashedScope = Model::TRASHED_INCLUDE;

    return $this;
  }

  /**
   * 查询范围：仅查询已软删除的记录（WHERE deleted_at IS NOT NULL）
   *
   * @return $this
   */
  public function onlyTrashed(): static
  {
    $this->trashedScope = Model::TRASHED_ONLY;

    return $this;
  }

  /**
   * 查询范围：仅查询未软删除的记录（WHERE deleted_at IS NULL，默认行为）
   *
   * @return $this
   */
  public function withoutTrashed(): static
  {
    $this->trashedScope = Model::TRASHED_EXCLUDE;

    return $this;
  }

  /**
   * 应用全局作用域（当前只有软删除过滤）
   *
   * 延迟到首个执行方法前调用一次，因此 withTrashed()/onlyTrashed()
   * 可以在链的任意位置覆盖作用域。
   */
  private function applyScopes(): void
  {
    $this->scopesApplied = true;

    if (!$this->model->usesSoftDelete()) {
      return;
    }

    $column = $this->model->tableName . '.' . $this->model->getDeleteTime();

    match ($this->trashedScope) {
      Model::TRASHED_EXCLUDE => $this->query->whereNull($column),
      Model::TRASHED_ONLY    => $this->query->whereNotNull($column),
      Model::TRASHED_INCLUDE => null,
    };
  }

  /**
   * 将 Query 查询结果转换为 Model 实例并执行 eager loading
   *
   * @param string $method 终端方法名
   * @param mixed  $result Query 查询结果
   * @return mixed
   */
  private function hydrateResult(string $method, mixed $result): mixed
  {
    if ($result === null || $result === false || (is_array($result) && empty($result))) {
      return $result;
    }

    switch ($method) {
      case 'first':
        $model = $this->model->rowToModel($result);
        if ($model) {
          $this->model->eagerLoadRelations([$model]);
        }
        return $model;

      case 'get':
      case 'all':
        $models = array_map(fn($row) => $this->model->rowToModel($row), $result);
        $this->model->eagerLoadRelations($models);
        return $models;

      case 'paginate':
        if ($result instanceof Paginator) {
          $items  = $result->getItems();
          $models = array_map(fn($row) => $this->model->rowToModel($row), $items);
          $this->model->eagerLoadRelations($models);
          $result->setItems($models);
        }
        return $result;

      default:
        return $result;
    }
  }

  /**
   * 强制真删除：绕过软删除作用域，按当前条件执行 DELETE
   *
   * 提供此方法是为了保持 `Model::where(...)->forceDelete()` 这类链式写法可用
   * （链式方法现在返回 Builder 而非 Model，Model 专有方法需在此显式提供）。
   *
   * 注意：不应用软删除作用域，否则已软删除的记录永远删不掉。
   *
   * @param array $params 预处理参数
   * @return int|bool 影响行数
   */
  public function forceDelete($params = []): int|bool
  {
    $this->trashedScope = Model::TRASHED_INCLUDE;

    return $this->query->delete($params);
  }

  /**
   * 获取底层 Query 实例
   *
   * 供 Relation 等组件基于已配置作用域的 Query 继续构建关联查询。
   *
   * @return Query
   */
  public function getQuery(): Query
  {
    return $this->query;
  }

  /**
   * 获取带全局作用域的 Query 实例
   *
   * 立即应用作用域后返回，用于需要脱离 Builder 直接使用 Query 的场景（如 Relation）。
   *
   * @return Query
   */
  public function toQuery(): Query
  {
    if (!$this->scopesApplied) {
      $this->applyScopes();
    }

    return $this->query;
  }

  /**
   * 获取原型 Model 实例
   *
   * @return Model
   */
  public function getModel(): Model
  {
    return $this->model;
  }

  /**
   * 获取当前声明的预加载关系
   *
   * @return array<string>
   */
  public function getEagerLoads(): array
  {
    return $this->eagerLoads;
  }
}
