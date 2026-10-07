<?php

namespace kernel\Modules\DiscuzX\Foundation\Database;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\ModelBuilder;
use kernel\Foundation\Database\PDO\Query;

/**
 * DiscuzX 模型基类
 *
 * 作用：让 kernel 的模型层（Model / ModelBuilder / Query 链式 API）在 DiscuzX 环境下
 * **全部落到 Discuz 自己的数据库操作上**（`\DB`），而不是 kernel 的 PDO 驱动。
 *
 * 实现方式：
 * - 构造时把表名交给 `DiscuzXQuery`（内部补 Discuz 表前缀），DB 门面指向 `DiscuzXDB`；
 * - 覆写 `query()`，保证所有查询入口（含 `ModelBuilder` 内部的 `$model->query()`）
 *   都返回 `DiscuzXQuery`，从而由 `DiscuzXQuery` 把「构建 SQL + `\DB` 执行」串起来；
 * - **不再覆写** insert / update / delete / getAll / getOne / count … 这些方法：
 *   它们经 kernel `Model::__call()` → `ModelBuilder` → `DiscuzXQuery` 落到 Discuz DB，
 *   由 `DiscuzXQuery` 统一实现（见该类）。
 *
 * 表名规则（与 `DiscuzXQuery::realTableName()` 一致，幂等）：
 * 传入未加前缀的表名（如 `forum_attachment`）会自动补成 `pre_forum_attachment`；
 * 传入已含前缀的完整表名则原样使用。
 *
 * 时间戳：统一走 kernel 机制（`$casts` + `$createTime`/`$updateTime` + `$timestamps`），
 * 构造时会探测时间戳列是否存在，缺失则自动关闭，避免向 Discuz 表写入不存在的列。
 *
 * 主键：沿用 kernel 默认值 `id`。若目标表主键不是 `id`（如 `forum_attachment` 为 `aid`），
 * 请在子类覆写 `protected $primaryKey = "aid";`，否则 `find()` / AR 的 `save()`、`delete()`
 * 会按错误的列定位记录。
 *
 * 用法：
 * ```php
 * class GStudioFilesModel extends DiscuzXModel
 * {
 *   public $tableName = "gstudio_files";   // 无需写 pre_ 前缀
 * }
 *
 * GStudioFilesModel::where("uid", 1)->orderBy("id", "DESC")->limit(10)->getAll();
 * GStudioFilesModel::where("uid", 1)->getOne();
 * GStudioFilesModel::where("uid", 1)->count();
 * GStudioFilesModel::where("uid", 1)->exist();
 * GStudioFilesModel::where("uid", 1)->update(["status" => 1]);
 *
 * $model = new GStudioFilesModel();
 * $model->insert(["uid" => 1, "name" => "a.txt"]);
 * ```
 *
 * ------------------------------------------------------------------
 * === 查询链式方法（转发给 ModelBuilder，返回 $this 以便继续链式调用）===
 * @method ModelBuilder where(string|array|callable $column, mixed $operatorOrValue = null, mixed $value = null)
 * @method ModelBuilder orWhere(string|array|callable $column, mixed $operatorOrValue = null, mixed $value = null)
 * @method ModelBuilder whereRaw(string $sql)
 * @method ModelBuilder orWhereRaw(string $sql)
 * @method ModelBuilder whereIn(string $column, array|Query $values)
 * @method ModelBuilder whereNotIn(string $column, array|Query $values)
 * @method ModelBuilder whereBetween(string $column, mixed $min, mixed $max)
 * @method ModelBuilder whereNotBetween(string $column, mixed $min, mixed $max)
 * @method ModelBuilder whereNull(string $column)
 * @method ModelBuilder whereNotNull(string $column)
 * @method ModelBuilder whereLike(string $column, string $value)
 * @method ModelBuilder whereNotLike(string $column, string $value)
 * @method ModelBuilder whereColumn(string $column1, string $operator, string $column2)
 * @method ModelBuilder whereExists(Query|callable $queryOrCallable)
 * @method ModelBuilder whereNotExists(Query|callable $queryOrCallable)
 * @method ModelBuilder whereDate(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereYear(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereMonth(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereDay(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereTime(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereHour(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereMinute(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereSecond(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method ModelBuilder whereFilter(array $data, string $operator = 'AND') 按数组批量加条件（值为 null/'' 的条件自动忽略）
 * @method ModelBuilder addSelect(string ...$columns) 追加查询字段（注意：`select()` 是 kernel Table 的原生 SQL 方法，不走查询链）
 * @method ModelBuilder selectRaw(string $sql)
 * @method ModelBuilder distinct(string ...$columns)
 * @method ModelBuilder orderBy(string $column, string $direction = 'ASC')
 * @method ModelBuilder orderByRaw(string $rawSQL)
 * @method ModelBuilder orderRandom(string $seed = null)
 * @method ModelBuilder groupBy(string ...$columns)
 * @method ModelBuilder groupByRaw(string $rawSQL)
 * @method ModelBuilder limit(int $value)
 * @method ModelBuilder take(int $value)
 * @method ModelBuilder offset(int $value)
 * @method ModelBuilder skip(int $value)
 * @method ModelBuilder page(int $page, int $perPage = 10)
 * @method ModelBuilder from(string $tableName, string $asName = null)
 * @method ModelBuilder when(mixed $condition, callable $callback, ?callable $default = null)
 * @method ModelBuilder unless(mixed $condition, callable $callback, ?callable $default = null)
 * @method ModelBuilder map(callable $callback)
 * @method ModelBuilder reset() 重置查询状态（表名保留）
 * ------------------------------------------------------------------
 * === 取数（终端方法，结束链式调用，返回结果）===
 * @method array getAll(array $params = []) 取全部行（DiscuzX 别名，等价于 get()）
 * @method array get(array $params = []) 取全部行
 * @method array|null getOne(array $params = []) 取第一行（DiscuzX 别名，等价于 first()）
 * @method array|null first(array $params = []) 取第一行，无结果返回 null
 * @method int count(string $column = '*') 统计行数
 * @method int|float max(string $column) 最大值
 * @method int|float min(string $column) 最小值
 * @method int|float avg(string $column) 平均值
 * @method int|float sum(string $column) 求和
 * @method bool exists() 是否存在满足条件的记录
 * @method bool exist() 是否存在（DiscuzX 别名，等价于 exists()）
 * @method mixed value(string $column) 取单列值
 * @method array pluck(string $column, string $indexKey = null) 取单列的值集合
 * ------------------------------------------------------------------
 * === 写操作 ===
 * @method bool insert(array $data, bool $isReplaceInto = false, bool $isIgnore = false) 插入，自增 ID 用 insertId() 取
 * @method int insertGetId(array $data, bool $isReplaceInto = false, bool $isIgnore = false) 插入并返回自增 ID
 * @method bool update(array $data) 更新（需先 where()，否则更新全表）
 * @method bool batchUpdate(array $fieldNames, array $values) 批量更新（REPLACE INTO 语义：未给出的列会丢失）
 * @method bool increment(string $field, int $value = 1) 字段自增
 * @method bool decrement(string $field, int $value = 1) 字段自减
 * （delete / forceDelete / save / insertId / find 等为父类真实方法或 __call 特例，见 kernel Model；
 *   其中 delete 默认软删除、forceDelete 直接 DELETE、insertId 取最后插入的自增 ID）
 * ------------------------------------------------------------------
 * === 静态入口（__callStatic 自动实例化后转发，语义同实例方法）===
 * @method static ModelBuilder where(mixed $column, mixed $operatorOrValue = null, mixed $value = null)
 * @method static ModelBuilder whereFilter(array $data, string $operator = 'AND')
 * @method static ModelBuilder orderBy(string $column, string $direction = 'ASC')
 * @method static ModelBuilder limit(int $value)
 * @method static array getAll()
 * @method static array get()
 * @method static array|null getOne()
 * @method static array|null first()
 * @method static int count(string $column = '*')
 * @method static bool exists()
 * @method static bool exist()
 * @method static array pluck(string $column, string $indexKey = null)
 * @method static static find(int|string $id) 按主键查一行，数据回填到实例（需保证 $primaryKey 正确）
 *
 * @see DiscuzXQuery 负责「构建 SQL + 走 \DB 执行」的核心
 * @see DiscuzXDB    Discuz 数据库门面（兼容 kernel Table / Query 的执行接口）
 * @see Model        kernel 模型基类（AR、类型转换、关联）
 */
class DiscuzXModel extends Model
{
  /**
   * 调试开关：为 true 时查询方法只返回 SQL 字符串，不真正执行
   *
   * 通过 `dryRun(true)` 开启；`query()` 会把该开关传递给新建的 `DiscuzXQuery`，
   * 因此经 ModelBuilder 链式调用同样生效。
   *
   * @var bool
   */
  protected $dryRun = false;

  /**
   * 构造模型
   *
   * 不调用 `parent::__construct()`：kernel 的父类构造会套用 kernel 的表前缀配置、
   * 并索取 PDO 驱动，这两者在 DiscuzX 环境下都不适用。这里只做 DiscuzX 需要的三件事：
   * 记录表名、创建 `DiscuzXQuery`、把 DB 门面指向 `DiscuzXDB`，
   * 并补齐父类构造期本该完成的时间戳探测。
   *
   * @param string|null $tableName 表名（可不含 `pre_` 前缀）；为 null 时先取 `$this->tableName`，
   *                               仍为空则按类名推导（与 kernel `Model` 一致，
   *                               如 `CommonMemberProfileModel` → `common_member_profile`）
   * @param string|null $prefix    前缀，传入时与 $tableName 以 `_` 连接（供 DiscuzXAddonModel 使用）
   */
  function __construct($tableName = null, $prefix = null)
  {
    if (!$tableName) {
      $tableName = $this->tableName;
    }
    if (!$tableName) {
      // 与 kernel Model::__construct 一致的兜底：既没声明 $tableName 也没传参时按类名推导，
      // 使「空子类 + 静态链式调用」也能工作，例如：
      //   class CommonMemberProfileModel extends DiscuzXModel {}
      //   CommonMemberProfileModel::where("uid", $uids)->getAll();
      $tableName = static::getDefaultTableName();
    }
    if ($prefix) {
      $tableName = join("_", [$prefix, $tableName]);
    }

    $this->tableName = $tableName;
    $this->query = new DiscuzXQuery($tableName);
    $this->DB = DiscuzXDB::class;

    // 未调用 parent::__construct()（避免 kernel 的表前缀与 PDO 配置介入），
    // 因此这里手工补齐构造期本该完成的时间戳探测，避免 AR save() 写入不存在的时间列。
    $this->detectTimestampsSupport();
  }

  /**
   * 读取或设置 dryRun（调试用）
   *
   * 读写一体：传 $val 时设置开关并返回 `$this`（链式）；不传时返回当前开关值。
   *
   * @param bool|null $val 是否开启调试模式；null 表示仅读取
   * @return $this|bool 设置时返回 $this，读取时返回 bool
   */
  function dryRun($val = null)
  {
    if (!is_null($val)) {
      $this->dryRun = (bool) $val;
      return $this;
    }
    return $this->dryRun;
  }

  /**
   * 查询入口：始终返回 DiscuzXQuery
   *
   * kernel 的 `ModelBuilder` 会通过 `$model->query()` 取查询实例，
   * 这里统一返回 `DiscuzXQuery`，使整条链路的执行都落到 Discuz 的 `\DB`。
   * dryRun 开启时同样传递给新建的查询实例。
   *
   * @return Query 实际为 {@see DiscuzXQuery}（其执行方法走 `\DB`，而非 kernel 的 PDO 驱动）
   */
  public function query(): Query
  {
    $query = new DiscuzXQuery($this->tableName);
    if ($this->dryRun) {
      $query->dryRun(true);
    }
    return $query;
  }

  /**
   * 探测本表是否具备 kernel 时间戳列，并据此开关自动时间戳
   *
   * 与 kernel `Model::detectTimestamps()` 等价：当 `$casts` 中未声明
   * `$createTime` / `$updateTime` 时关闭自动时间戳，避免向 Discuz 表写入不存在的列。
   * （Discuz 核心表通常没有 `created_at` / `updated_at` 列。）
   *
   * 若目标表确有 kernel 风格的时间戳列，在子类 `$casts` 中声明这两个字段即可自动开启。
   *
   * @return void
   */
  protected function detectTimestampsSupport()
  {
    $hasCreate = array_key_exists($this->createTime, $this->casts);
    $hasUpdate = array_key_exists($this->updateTime, $this->casts);
    if ($hasCreate && $hasUpdate) {
      return;
    }
    $this->timestamps = false;
    $this->queryTimestamps = false;
  }
}
