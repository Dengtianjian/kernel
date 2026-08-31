<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Database\PDO\Relation\HasOne;
use kernel\Foundation\Database\PDO\Relation\HasMany;
use kernel\Foundation\Database\PDO\Relation\BelongsTo;
use kernel\Foundation\Database\PDO\Relation\Relation;

/**
 * Model — AR（Active Record）+ Query 代理 ORM 基类
 *
 * 提供两种使用模式：
 * 1. Query 代理：所有未定义方法通过 __call / __callStatic 自动转发给底层 Query 对象，支持完整链式调用
 * 2. Active Record：直接操作单行数据，$model->field = value → save() / find() / delete()
 *
 * 类型转换架构：
 *   PHP 赋值/DB 取值 ──→ castToDb()  ──→  存入 $data（DB 兼容格式）
 *   读取/输出        ──→ castFromDb() ──→  转为 PHP 期望类型
 *
 * 时间戳自动维护：save() 时自动填充 created_at / updated_at，精度由字段的 cast 类型决定
 * 软删除：delete() 写入 deleted_at 而非真删，查询默认过滤 deleted_at IS NULL
 *
 * ------------------------------------------------------------------
 * === Active Record 方法 ===
 * @method static static find(int|string $id)                  按主键查一行，数据填充到 $data
 * @method        $this save()                                 主键非默认值 → UPDATE，否则 → INSERT + 回填 ID
 * @method        int|bool delete(array $params = [])          软删除（写 deleted_at）或真删；有主键按主键，否则走 Query 条件
 * ------------------------------------------------------------------
 * === Query 链式方法（返回值 $this，可继续链式调用）===
 * @method $this where(string $column, mixed $operatorOrValue = null, mixed $value = null)
 * @method $this whereRaw(string $sql)
 * @method $this whereBetween(string $column, mixed $min, mixed $max)
 * @method $this whereNotBetween(string $column, mixed $min, mixed $max)
 * @method $this whereIn(string $column, array|\kernel\Foundation\Database\PDO\Query $values)
 * @method $this whereNotIn(string $column, array|\kernel\Foundation\Database\PDO\Query $values)
 * @method $this whereNull(string $column)
 * @method $this whereNotNull(string $column)
 * @method $this whereLike(string $column, string $value)
 * @method $this whereNotLike(string $column, string $value)
 * @method $this whereColumn(string $column1, string $operatorOrColumn2, string $column2 = null)
 * @method $this whereDate(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereYear(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereMonth(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereDay(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereTime(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereHour(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereMinute(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereSecond(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this whereExists(\kernel\Foundation\Database\PDO\Query|callable $queryOrCallable)
 * @method $this whereNotExists(\kernel\Foundation\Database\PDO\Query|callable $queryOrCallable)
 * @method $this orWhere(string $column, mixed $operatorOrValue = null, mixed $value = null)
 * @method $this orWhereRaw(string $sql)
 * @method $this orWhereBetween(string $column, mixed $min, mixed $max)
 * @method $this orWhereNotBetween(string $column, mixed $min, mixed $max)
 * @method $this orWhereIn(string $column, array|\kernel\Foundation\Database\PDO\Query $values)
 * @method $this orWhereNotIn(string $column, array|\kernel\Foundation\Database\PDO\Query $values)
 * @method $this orWhereNull(string $column)
 * @method $this orWhereNotNull(string $column)
 * @method $this orWhereLike(string $column, string $value)
 * @method $this orWhereNotLike(string $column, string $value)
 * @method $this orWhereColumn(string $column1, string $operatorOrColumn2, string $column2 = null)
 * @method $this orWhereDate(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereYear(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereMonth(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereDay(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereTime(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereHour(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereMinute(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereSecond(string $column, string $operatorOrValue, mixed $value = null)
 * @method $this orWhereExists(\kernel\Foundation\Database\PDO\Query|callable $queryOrCallable)
 * @method $this orWhereNotExists(\kernel\Foundation\Database\PDO\Query|callable $queryOrCallable)
 * @method $this whereFilter(array $data, string $operator = 'AND')
 * @method $this select(string ...$columns)
 * @method $this selectRaw(string $sql)
 * @method $this addSelect(string ...$columns)
 * @method $this selectSub(\kernel\Foundation\Database\PDO\Query|callable $query, string $asName)
 * @method $this distinct(string ...$columns)
 * @method $this orderBy(string $column, string $direction = 'ASC')
 * @method $this orderByRaw(string $rawSQL)
 * @method $this orderRandom(string $seed = null)
 * @method $this groupBy(string ...$columns)
 * @method $this groupByRaw(string $rawSQL)
 * @method $this limit(int $value)
 * @method $this take(int $value)
 * @method $this offset(int $value)
 * @method $this skip(int $value)
 * @method $this page(int $page, int $perPage = 10)
 * @method $this from(string $tableName, string $asName = null)
 * @method $this fromSub(\kernel\Foundation\Database\PDO\Query|callable $query, string $asName)
 * @method $this bind(string $key, mixed $value)
 * @method $this addBindings(array $bindings)
 * @method $this fill(string $executeType, array $options)
 * @method $this notReset()
 * @method $this reset()
 * @method $this setDatabaseDriver($driver)
 * ------------------------------------------------------------------
 * === Query 终端方法（结束链式调用，直接返回结果）===
 * @method array|false                   first(array $params = [])
 * @method array                         get(array $params = [])
 * @method \Generator                    cursor(array $params = [])
 * @method bool                          chunk(int $size, callable $callback)
 * @method bool                          chunkById(int $size, callable $callback, string $column = 'id')
 * @method \Generator                    chunkStream(int $size, string $column = 'id')
 * @method int|false                     count(string $column = '*', array $params = [])
 * @method int|float|false               max(string $column, array $params = [])
 * @method int|float|false               min(string $column, array $params = [])
 * @method float|false                   avg(string $column, array $params = [])
 * @method int|float|false               sum(string $column, array $params = [])
 * @method bool                          exists(array $params = [])
 * @method bool                          notExists(array $params = [])
 * @method mixed|null                    value(string $column, array $params = [])
 * @method array                         pluck(string $column, string|null $indexKey = null, array $params = [])
 * @method \kernel\Foundation\Database\PDO\Paginator paginate(array $params = [])
 * @method \kernel\Foundation\Database\PDO\Statement raw(mixed $sql)
 * @method string                        getSQL()
 * @method array                         getBindings()
 * @method string                        writeSql(string $type, array|null $data = null, array $options = [])
 * ------------------------------------------------------------------
 * === Query 写方法 ===
 * @method int|bool     insert(array $data, bool $isReplaceInto = false, bool $isIgnore = false, bool $returnId = false, array $params = [])
 * @method int|string   insertGetId(array $data, bool $isReplaceInto = false, bool $isIgnore = false, array $params = [])
 * @method int|bool     update(array $data, array $params = [])
 */
class Model extends Table
{
  /** @var string 数据库表名（构造时可由入参覆盖，未指定则从类名自动推断） */
  public $tableName = "";

  /** @var Query 底层查询构建器实例 */
  protected $query;

  /**
   * 手动指定的字段类型映射（字段名 → 类型标签）
   *
   * 支持的类型：
   *   int, float, bool, string, array              — 基础类型
   *   timestamp, timestamp_ms                      — 时间戳（库中存格式化字符串）
   *   unixtime, unixtime_ms                        — 时间戳（库中存整数，秒级/毫秒级）
   *   date                                          — 日期（输出格式默认取 $dateFormat）
   *   date|Y-m-d, date|d/m/Y, ...                  — 日期 + 自定义输出格式
   *
   * 仅输出时生效；写库时一律按字段实际类型（$schemaCasts 优先）转换。
   *
   * @var array<string, string>
   */
  protected $casts = [];

  /**
   * 从 $schema 列定义自动推导的类型映射（字段名 → 类型标签）
   *
   * 优先级低于 $casts：写入 DB 时优先用 $schemaCasts 决定转换方式，
   * 输出时只用 $casts 做格式化。
   *
   * @var array<string, string>
   */
  private $schemaCasts = [];

  /** @var string 主键字段名。若未显式覆盖，构造时从 $schema 自动检测 */
  protected $primaryKey = 'id';

  /** @var bool save() 时是否自动注入 created_at / updated_at */
  protected $timestamps = true;

  /** @var string 创建时间字段名 */
  protected $createTime = 'created_at';

  /** @var string 更新时间字段名 */
  protected $updateTime = 'updated_at';

  /** @var bool 是否启用软删除（delete() 写 deleted_at 而非真删） */
  protected $softDelete = true;

  /** @var string 软删除标记字段名 */
  protected $deleteTime = 'deleted_at';

  /**
   * timestamp / date 类型默认输出格式
   *
   * 仅在未通过 'date|格式' 显式指定输出格式时生效。
   *
   * @var string
   */
  protected $dateFormat = 'Y-m-d H:i:s';

  /**
   * 软删除过滤是否生效
   *
   * 默认 true（查询自动加 WHERE deleted_at IS NULL）。
   * 调用 withTrashed() / onlyTrashed() 后置为 false。
   *
   * @var bool
   */
  private $softDeleteActive = true;

  /**
   * 当前行数据（键值对，存储的是 DB 兼容格式）
   *
   * 构造时按 $casts 填充各字段默认值。
   * 写入通过 __set → castToDb 转换后存入；读取通过 __get → castFromDb 转换后返回。
   *
   * @var array<string, mixed>
   */
  private $data = [];

  /**
   * 已加载的关联数据缓存
   *
   * 懒加载或 eager loading 完成后将结果存入此数组，
   * 后续通过 __get 访问同名属性时直接返回缓存，避免重复查询。
   *
   * @var array<string, mixed>
   */
  private $relations = [];

  /**
   * 待预加载的关联关系名列表
   *
   * 通过 with() 静态方法设置，在 get/first/find 等终端方法执行时
   * 触发批量 eager loading。
   *
   * @var array<string>
   */
  private $eagerLoads = [];

  // ===================================================================
  // 构造 & 初始化
  // ===================================================================

  public function __construct($tableName = null)
  {
    if ($tableName) {
      $this->tableName = $tableName;
    }
    if (empty($this->tableName)) {
      $this->tableName = static::getDefaultTableName();
    }
    $this->tableName = $this->prefix($this->tableName);
    $this->query     = new Query($this->tableName);

    // 1. 从 $schema 列定义推导字段类型 → $schemaCasts
    if (!empty($this->schema)) {
      $this->schemaCasts = $this->getPhpSchema();
    }

    // 2. 为 $casts 中声明的字段填充默认值
    foreach ($this->casts as $field => $type) {
      $this->data[$field] = $this->castDefault($type);
    }

    // 3. 自动检测主键（遍历 $schema，找到首个 PRIMARY / AUTO_INCREMENT 列）
    $this->detectPrimaryKey();

    // 4. 若 $casts / $schemaCasts 中不存在时间戳或软删除列，自动关闭对应功能
    $this->detectTimestamps();
    $this->detectSoftDelete();

    // 5. 开启软删除过滤：后续查询自动追加 WHERE deleted_at IS NULL
    $this->applySoftDeleteScope();

    parent::__construct();
  }

  /**
   * 遍历 $schema 找到首个主键或自增列，设置为 $primaryKey
   */
  private function detectPrimaryKey(): void
  {
    if (empty($this->schema)) {
      return;
    }
    foreach ($this->schema as $col) {
      if ($col instanceof Schema && ($col->isPrimary() || $col->isAutoIncrement())) {
        $this->primaryKey = $col->getName();
        return;
      }
    }
  }

  /**
   * 如果 $casts 和 $schemaCasts 中都不存在 $deleteTime 字段，则关闭软删除
   */
  private function detectSoftDelete(): void
  {
    if (!$this->softDelete) {
      return;
    }
    if (
      array_key_exists($this->deleteTime, $this->casts)
      || array_key_exists($this->deleteTime, $this->schemaCasts)
    ) {
      return;
    }
    if (empty($this->schema) && empty($this->casts)) {
      return;
    }
    $this->softDelete = false;
  }

  /**
   * 如果 $casts 和 $schemaCasts 中不存在 $createTime 或 $updateTime，则关闭时间戳自动维护
   */
  private function detectTimestamps(): void
  {
    if (!$this->timestamps) {
      return;
    }
    $hasCreate = array_key_exists($this->createTime, $this->casts)
      || array_key_exists($this->createTime, $this->schemaCasts);
    $hasUpdate = array_key_exists($this->updateTime, $this->casts)
      || array_key_exists($this->updateTime, $this->schemaCasts);
    if ($hasCreate && $hasUpdate) {
      return;
    }
    if (empty($this->schema) && empty($this->casts)) {
      return;
    }
    $this->timestamps = false;
  }

  // ===================================================================
  // 类型转换引擎
  //
  // 两条单向管道：
  //   castToDb()   ：PHP 值 → DB 兼容格式（写入时调用）
  //   castFromDb() ：DB 兼容格式 → PHP 期望类型（读取/输出时调用）
  //
  // 辅助工具：
  //   parseTimestamp() ：任意时间输入 → Unix 时间戳 int
  //   formatTimestamp()：Unix 时间戳 + 格式 → 日期字符串
  // ===================================================================

  /**
   * PHP → DB：将任意 PHP 值转为可直接写入数据库的格式
   *
   * 转换规则：
   *   int/float/bool/string → 强转
   *   array                 → JSON 编码
   *   timestamp             → $dateFormat 格式化字符串（如 'Y-m-d H:i:s'）
   *   timestamp_ms          → 'Y-m-d H:i:s.v' 格式化字符串（含毫秒）
   *   unixtime              → 秒级时间戳 int（不格式化，库中即整数）
   *   unixtime_ms           → 毫秒级时间戳 int（不格式化，库中即整数）
   *   date                  → 'Y-m-d' 格式化字符串（MySQL DATE 标准格式）
   *
   * @param string $type  类型标签
   * @param mixed  $value 任意 PHP 值
   * @return mixed 数据库兼容值
   */
  private function castToDb(string $type, mixed $value): mixed
  {
    ['base' => $baseType] = $this->parseCastType($type);
    return match ($baseType) {
      'int'           => (int) $value,
      'float'         => (float) $value,
      'bool'          => (bool) $value,
      'string'        => (string) $value,
      'array'         => json_encode($value, JSON_UNESCAPED_UNICODE),
      'timestamp'     => $this->formatTimestamp($this->dateFormat, $this->parseTimestamp($value)),
      'timestamp_ms'  => ($tsMs = $this->parseTimestamp($value, 'ms')) === null
        ? null
        : $this->formatTimestamp('Y-m-d H:i:s.v', (int) ($tsMs / 1000), ($tsMs % 1000) / 1000),
      'unixtime'      => $this->parseTimestamp($value, 's'),
      'unixtime_ms'   => $this->parseTimestamp($value, 'ms'),
      'date'          => $this->formatTimestamp('Y-m-d', $this->parseTimestamp($value)),
      default         => $value,
    };
  }

  /**
   * DB → PHP：将数据库原始值转为 PHP 端期望的类型（输出/读取时调用）
   *
   * 转换规则：
   *   int/float/bool/string → 强转
   *   array                 → JSON 解码为数组
   *   timestamp             → Unix 秒级时间戳 int
   *   timestamp_ms          → Unix 毫秒级时间戳 int
   *   unixtime              → 秒级时间戳 int（库中已是整数，不做日期解析）
   *   unixtime_ms           → 毫秒级时间戳 int（库中已是整数，不做日期解析）
   *   date                  → 格式化日期字符串（'date|格式' 自定义 > $dateFormat 默认）
   *
   * @param string $type  类型标签（支持 'date|Y-m-d' 指定自定义输出格式）
   * @param mixed  $value DB 中取出的原始值
   * @return mixed PHP 期望类型值
   */
  private function castFromDb(string $type, mixed $value): mixed
  {
    ['base' => $baseType, 'format' => $customFormat] = $this->parseCastType($type);
    return match ($baseType) {
      'int'           => (int) $value,
      'float'         => (float) $value,
      'bool'          => (bool) $value,
      'string'        => (string) $value,
      'array'         => is_array($value) ? $value : json_decode($value, true) ?? [],
      'timestamp'     => $this->parseTimestamp($value, 's'),
      'timestamp_ms'  => $this->parseTimestamp($value, 'ms'),
      'unixtime'      => ($value === null || $value === '') ? null : (int) $value,
      'unixtime_ms'   => ($value === null || $value === '') ? null : (int) $value,
      'date'          => $this->formatTimestamp($customFormat ?? $this->dateFormat, $this->parseTimestamp($value)),
      default         => $value,
    };
  }

  /**
   * 解析 cast 类型标签，分离基础类型和可选的格式参数
   *
   *      'date|Y-m-d'  → ['base' => 'date', 'format' => 'Y-m-d']
   *      'timestamp'   → ['base' => 'timestamp', 'format' => null]
   *
   * @return array{base: string, format: string|null}
   */
  private function parseCastType(string $type): array
  {
    if (preg_match('/^(date)\|(.+)$/', $type, $m)) {
      return ['base' => $m[1], 'format' => $m[2]];
    }
    return ['base' => $type, 'format' => null];
  }

  /**
   * 类型默认值（用于 $data 字段初始化）
   *
   *   int/float/unixtime/unixtime_ms → 0; bool → false; array → []; timestamp/date → null; 其他 → ''
   *
   * unixtime/unixtime_ms 取 0（与整数列语义一致，时间戳非负）；
   * 若字段可空且希望「未设置」为 null，请显式赋值 null 或改用 timestamp 系列。
   */
  private function castDefault(string $type): mixed
  {
    $baseType = $this->parseCastType($type)['base'];
    return match ($baseType) {
      'int',
      'unixtime',
      'unixtime_ms'   => 0,
      'float'         => 0.0,
      'bool'          => false,
      'array'         => [],
      'timestamp',
      'timestamp_ms',
      'date'          => null,
      default         => '',
    };
  }

  /**
   * 任意格式的时间输入 → 指定精度的 Unix 时间戳 int
   *
   * 支持的输入格式：
   *   null / ''                          → null
   *   DateTime / Carbon 对象             → 从对象提取
   *   日期字符串（含可选亚秒，如 '2026-07-16 10:30:00.123'）→ strtotime 解析
   *   纯数字 ≤10 位                      → 当作秒级时间戳
   *   纯数字 >10 位                      → 当作毫秒级时间戳
   *
   * @param string $unit 返回精度：'s' 秒（默认），'ms' 毫秒
   * @return int|null Unix 时间戳，无法解析则返回 null
   */
  private function parseTimestamp(mixed $value, string $unit = 's'): ?int
  {
    if ($value === null || $value === '') {
      return null;
    }
    if ($value instanceof \DateTimeInterface) {
      $ts    = (int) $value->format('U');
      $micro = (int) $value->format('u') / 1000000;
      return $unit === 'ms' ? (int) ($ts * 1000 + round($micro * 1000)) : $ts;
    }
    if (is_string($value) && !ctype_digit($value)) {
      $micro = 0.0;
      if (preg_match('/\.(\d+)/', $value, $m)) {
        $micro = (float) ('0.' . $m[1]);
      }
      $ts = strtotime($value);
      if ($ts !== false) {
        return $unit === 'ms' ? (int) ($ts * 1000 + round($micro * 1000)) : $ts;
      }
    }
    if (is_numeric($value)) {
      $intVal = (int) $value;
      if (strlen((string) $intVal) > 10) {
        $ts    = (int) ($intVal / 1000);
        $micro = ($intVal % 1000) / 1000;
        return $unit === 'ms' ? (int) ($ts * 1000 + round($micro * 1000)) : $ts;
      }
      return $unit === 'ms' ? $intVal * 1000 : $intVal;
    }
    return null;
  }

  /**
   * Unix 秒级时间戳 + 亚秒 → 格式化日期字符串
   *
   * 支持 date() 标准占位符 + 两个亚秒扩展：
   *   .v / v  → 毫秒（3 位）
   *   .u / u  → 微秒（6 位）
   *
   * 注意：$ts 必须是秒级时间戳，不能传入毫秒值。
   *
   * @param string   $format 日期格式串
   * @param int|null $ts     Unix 秒级时间戳，null 时返回 null
   * @param float    $micro  亚秒小数部分（0.0 ~ 0.999999）
   */
  private function formatTimestamp(string $format, ?int $ts, float $micro = 0.0): ?string
  {
    if ($ts === null) {
      return null;
    }
    // 剥离亚秒占位符，避免原生 date() 输出无意义的 '000'
    $clean = str_replace(['.v', 'v'], '', $format);
    $clean = str_replace(['.u', 'u'], '', $clean);
    $result = date($clean, $ts);

    // 按需拼接亚秒
    if (str_contains($format, '.u')) {
      $result .= '.' . sprintf('%06d', (int) round($micro * 1000000));
    } elseif (str_contains($format, 'u')) {
      $result .= sprintf('%06d', (int) round($micro * 1000000));
    } elseif (str_contains($format, '.v')) {
      $result .= '.' . sprintf('%03d', (int) round($micro * 1000));
    } elseif (str_contains($format, 'v')) {
      $result .= sprintf('%03d', (int) round($micro * 1000));
    }

    return $result;
  }

  /**
   * 获取手动声明的字段类型映射
   *
   * @return array<string, string>
   */
  public function getCasts(): array
  {
    return $this->casts;
  }

  /**
   * 从类名自动推断数据库表名
   *
   * 规则：去 Model 后缀 → 驼峰转下划线 → 全小写
   *
   *   UserModel        → user
   *   UserProfileModel → user_profile
   *   PostCategory     → post_category
   */
  protected static function getDefaultTableName(): string
  {
    $class = (new \ReflectionClass(static::class))->getShortName();
    $name  = preg_replace('/Model$/', '', $class);
    $name  = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
    return $name;
  }

  public function __clone()
  {
    $this->query = clone $this->query;
  }

  // ===================================================================
  // 方法转发：__callStatic / __call
  // ===================================================================

  /**
   * 静态调用代理：自动实例化后转发到实例方法
   *
   *   UserModel::where('status', 1)->first()  → new UserModel → __call('where') → __call('first')
   *   UserModel::count()                       → new UserModel → __call('count')
   */
  public static function __callStatic($method, $parameters)
  {
    return (new static())->$method(...$parameters);
  }

  /**
   * 实例方法代理：未定义方法自动转发给底层 Query 对象
   *
   * 若 Query 方法返回 Query 自身（链式方法如 where / orderBy），
   * 替换为 $this 以保证 Model 链不间断。
   *
   * 对于 get/first 等终端方法，自动检测并执行 eager loading。
   */
  public function __call($method, $parameters)
  {
    // find(id) 按主键查询，数据填充到当前实例（与 first 类似，但无需额外 query 链）
    if ($method === 'find') {
      $id = $parameters[0] ?? null;
      $row = $this->query->where($this->primaryKey, $id)->first();
      if ($row) {
        foreach ($row as $key => $value) {
          $this->$key = $value;  // __set → castToDb，数据存入 $data
        }
      }
      if (!empty($this->eagerLoads)) {
        $this->eagerLoadRelations([$this]);
      }
      return $this;
    }

    $result = $this->query->$method(...$parameters);

    if ($result === $this->query) {
      return $this;
    }

    // 终端方法：检测 eager loading
    $terminalMethods = ['get', 'all', 'first', 'value', 'pluck', 'paginate'];
    if (in_array($method, $terminalMethods) && !empty($this->eagerLoads)) {
      return $this->handleEagerLoads($method, $result);
    }

    return $result;
  }

  /**
   * 处理 eager loading
   *
   * 将查询结果转为 Model 实例，然后批量预加载关联数据。
   *
   * @param string $method 终端方法名
   * @param mixed  $result Query 查询结果
   * @return mixed
   */
  private function handleEagerLoads(string $method, mixed $result): mixed
  {
    if ($result === null || $result === false || (is_array($result) && empty($result))) {
      return $result;
    }

    switch ($method) {
      case 'first':
        $model = $this->rowToModel($result);
        if ($model) {
          $this->eagerLoadRelations([$model]);
        }
        return $model;

      case 'get':
      case 'all':
        $models = array_map(fn($row) => $this->rowToModel($row), $result);
        $this->eagerLoadRelations($models);
        return $models;

      case 'paginate':
        if ($result instanceof \kernel\Foundation\Database\PDO\Paginator) {
          $items = $result->getItems();
          $models = array_map(fn($row) => $this->rowToModel($row), $items);
          $this->eagerLoadRelations($models);
          $result->setItems($models);
        }
        return $result;

      default:
        return $result;
    }
  }

  /**
   * 批量执行 eager loading
   *
   * 对一组 Model 实例批量预加载指定的关联关系，消除 N+1 问题。
   *
   * @param array<static> $models Model 实例数组
   */
  private function eagerLoadRelations(array $models): void
  {
    if (empty($models) || empty($this->eagerLoads)) {
      return;
    }

    foreach ($this->eagerLoads as $relation) {
      if (!method_exists($this, $relation)) {
        continue;
      }

      /** @var Relation $rel */
      $rel = $this->$relation();
      if (!($rel instanceof Relation)) {
        continue;
      }

      $foreignKey   = $rel->getForeignKey();
      $localKey     = $rel->getLocalKey();
      $isBelongsTo  = $rel instanceof BelongsTo;

      // BelongsTo: 收集 parent 的 FK 值 → 用 related 的 PK 查询
      // HasOne/HasMany: 收集 parent 的 PK 值 → 用 related 的 FK 查询
      $collectKey = $isBelongsTo ? $foreignKey : $localKey;
      $queryKey   = $isBelongsTo ? $localKey   : $foreignKey;

      $keys = [];
      foreach ($models as $model) {
        $val = $model->{$collectKey};
        if ($val !== null) {
          $keys[] = $val;
        }
      }

      if (empty($keys)) {
        $emptyResult = $rel instanceof HasMany ? [] : null;
        foreach ($models as $model) {
          $model->setRelation($relation, $emptyResult);
        }
        continue;
      }

      $keys = array_unique($keys);

      // 创建新的关联 Query，批量查询
      $query = (new Query($rel->getQuery()->getTableName()))
        ->whereIn($queryKey, $keys);

      $rows = $query->get();

      // 按查询键分组
      $grouped = [];
      foreach ($rows as $row) {
        $key = $row[$queryKey] ?? null;
        if ($key !== null) {
          $grouped[$key][] = $row;
        }
      }

      // 分发给各 Model
      // BelongsTo: 用 parent.FK 匹配 related.PK 分组
      // HasOne/HasMany: 用 parent.PK 匹配 related.FK 分组
      $matchKey = $isBelongsTo ? $foreignKey : $localKey;
      $relatedClass = $rel->getRelatedClass();
      foreach ($models as $model) {
        $key = $model->{$matchKey};
        if ($key === null || !isset($grouped[$key])) {
          $model->setRelation($relation, $rel instanceof HasMany ? [] : null);
          continue;
        }

        if ($rel instanceof HasMany) {
          $items = [];
          foreach ($grouped[$key] as $row) {
            $items[] = $this->rowToModel($row, $relatedClass);
          }
          $model->setRelation($relation, $items);
        } else {
          $model->setRelation($relation, $this->rowToModel($grouped[$key][0], $relatedClass));
        }
      }
    }

    $this->eagerLoads = [];
  }

  /**
   * 将数据库行数据转为 Model 实例
   *
   * @param array  $row   数据库行数据
   * @param string $class Model 类名（默认当前类）
   * @return static
   */
  private function rowToModel(array $row, string $class = ''): Model
  {
    $class = $class ?: static::class;
    /** @var Model $instance */
    $instance = new $class();
    foreach ($row as $key => $value) {
      $instance->$key = $value;
    }
    return $instance;
  }

  /**
   * 设置关联数据缓存（用于 eager loading 分发）
   */
  public function setRelation(string $name, mixed $value): void
  {
    $this->relations[$name] = $value;
  }

  // ===================================================================
  // 属性读写：__get / __set
  // ===================================================================

  /**
   * 读取属性
   *
   * 优先级：类 property → 已缓存的关联数据 → 关系懒加载 → $data → null
   * 若字段在 $casts 中声明，通过 castFromDb 转为 PHP 输出格式。
   */
  public function __get($name)
  {
    if (property_exists($this, $name)) {
      return $this->$name;
    }

    // 已缓存的关联数据
    if (array_key_exists($name, $this->relations)) {
      return $this->relations[$name];
    }

    // 尝试懒加载关联关系
    if (method_exists($this, $name)) {
      $relation = $this->$name();
      if ($relation instanceof Relation) {
        return $this->relations[$name] = $relation->getResults();
      }
    }

    $value = $this->data[$name] ?? null;
    if ($value !== null && isset($this->casts[$name])) {
      $value = $this->castFromDb($this->casts[$name], $value);
    }
    return $value;
  }

  /**
   * 写入属性
   *
   * 自动通过 castToDb 转为 DB 兼容格式后存入 $data。
   * 类型优先级：$casts > $schemaCasts；均未声明则不转换，原样存储。
   */
  public function __set($name, $value)
  {
    $type = $this->casts[$name] ?? $this->schemaCasts[$name] ?? null;
    $this->data[$name] = $type ? $this->castToDb($type, $value) : $value;
  }

  // ===================================================================
  // Active Record：CRUD
  // ===================================================================

  /**
   * 持久化当前行数据
   *
   * 判断逻辑：主键值等于默认值（如 0） → INSERT 后回填自增 ID
   *           主键值非默认值              → UPDATE
   *
   * save() 前自动调用 touchTimestamps() 注入 created_at / updated_at。
   * $data 已存储为 DB 兼容格式，直接传给 Query::update / insertGetId。
   *
   * @return $this
   */
  public function save(): static
  {
    $pk       = $this->primaryKey;
    $pkValue  = $this->data[$pk] ?? null;
    $default  = $this->castDefault($this->schemaCasts[$pk] ?? $this->casts[$pk] ?? 'int');

    if ($pkValue !== $default) {
      // UPDATE
      $this->touchTimestamps();
      $this->query->where($pk, $pkValue)->update($this->data);
      return $this;
    }

    // INSERT
    $this->touchTimestamps(true);
    $id = $this->query->insertGetId($this->data);
    if ($id) {
      $this->data[$pk] = $id;
    }
    return $this;
  }

  /**
   * 获取当前时间戳（毫秒精度）
   *
   * 返回毫秒级 Unix 时间戳 int，由 castToDb 根据字段类型决定最终的 DB 存储格式。
   * 子类可覆盖以自定义时间来源。
   */
  protected function freshTimestamp(): int
  {
    $now = time();
    return (int) ($now * 1000 + (int) date('v'));
  }

  /**
   * 时间戳自动维护是否启用
   */
  protected function usesTimestamps(): bool
  {
    return $this->timestamps;
  }

  /**
   * 注入当前时间到 $data 的 created_at / updated_at 字段
   *
   * 通过 castToDb 转换，确保时间戳以正确的 DB 格式存储。
   *
   * @param bool $isInsert 是否 INSERT（true 时同时设 created_at；false 只设 updated_at）
   */
  private function touchTimestamps(bool $isInsert = false): void
  {
    if (!$this->usesTimestamps()) {
      return;
    }
    $createType = $this->schemaCasts[$this->createTime] ?? $this->casts[$this->createTime] ?? 'timestamp';
    $updateType = $this->schemaCasts[$this->updateTime] ?? $this->casts[$this->updateTime] ?? 'timestamp';
    $nowMs = $this->freshTimestamp();

    $this->data[$this->updateTime] = $this->castToDb($updateType, $nowMs);
    if ($isInsert) {
      $this->data[$this->createTime] = $this->castToDb($createType, $nowMs);
    }
  }

  // ===================================================================
  // 删除（含软删除）
  // ===================================================================

  /**
   * 删除记录
   *
   * 软删除启用时：写入 deleted_at = 当前时间（非真删）
   * 软删除关闭时：执行真实 DELETE
   *
   * data 中存在有效主键 → 按主键条件删；否则将条件转发给 Query（链式查询场景）。
   *
   * @return int|bool
   */
  public function delete($params = []): int|bool
  {
    $pk       = $this->primaryKey;
    $pkValue  = $this->data[$pk] ?? null;
    $default  = $this->castDefault($this->schemaCasts[$pk] ?? $this->casts[$pk] ?? 'int');

    // 软删除：写 deleted_at
    if ($this->usesSoftDelete()) {
      $deleteType = $this->schemaCasts[$this->deleteTime] ?? $this->casts[$this->deleteTime] ?? 'timestamp';
      $nowMs = $this->freshTimestamp();
      $dbValue = $this->castToDb($deleteType, $nowMs);
      $this->data[$this->deleteTime] = $dbValue;
      if ($pkValue !== $default) {
        return (bool) $this->query->where($pk, $pkValue)->update([
          $this->deleteTime => $dbValue,
        ]);
      }
      return (bool) $this->query->update([$this->deleteTime => $dbValue]);
    }

    // 真删除
    if ($pkValue !== $default) {
      return $this->query->where($pk, $pkValue)->delete($params);
    }
    return $this->query->delete($params);
  }

  /**
   * 强制真删除（绕过软删除，直接执行 DELETE）
   *
   * @return int|bool
   */
  public function forceDelete($params = []): int|bool
  {
    $pk       = $this->primaryKey;
    $pkValue  = $this->data[$pk] ?? null;
    $default  = $this->castDefault($this->schemaCasts[$pk] ?? $this->casts[$pk] ?? 'int');

    if ($pkValue !== $default) {
      return $this->query->where($pk, $pkValue)->delete($params);
    }
    return $this->query->delete($params);
  }

  /**
   * 恢复已软删除的记录（SET deleted_at = NULL）
   *
   * @return $this
   */
  public function restore(): static
  {
    $pk       = $this->primaryKey;
    $pkValue  = $this->data[$pk] ?? null;
    $default  = $this->castDefault($this->schemaCasts[$pk] ?? $this->casts[$pk] ?? 'int');

    if ($pkValue !== $default) {
      $this->withTrashed()->query->where($pk, $pkValue)->update([
        $this->deleteTime => null,
      ]);
      $this->data[$this->deleteTime] = null;
    }
    return $this;
  }

  /** 当前行是否已被软删除 */
  public function isTrashed(): bool
  {
    return !empty($this->data[$this->deleteTime]);
  }

  /** 软删除功能是否启用 */
  protected function usesSoftDelete(): bool
  {
    return $this->softDelete;
  }

  // ===================================================================
  // 软删除查询范围
  // ===================================================================

  /**
   * 查询范围：包含已软删除的记录
   *
   * 去掉自动追加的 WHERE deleted_at IS NULL，数据包含软删和未删。
   * 注意：会重置当前已构建的查询条件。
   *
   * @return $this
   */
  public function withTrashed(): static
  {
    $this->query->reset();
    $this->softDeleteActive = false;
    return $this;
  }

  /**
   * 查询范围：仅查询已软删除的记录（WHERE deleted_at IS NOT NULL）
   *
   * 注意：会重置当前已构建的查询条件。
   *
   * @return $this
   */
  public function onlyTrashed(): static
  {
    $this->query->reset();
    $this->softDeleteActive = false;
    $this->query->whereNotNull($this->tableName . '.' . $this->deleteTime);
    return $this;
  }

  /**
   * 在 Query 上全局应用软删除过滤（WHERE deleted_at IS NULL）
   *
   * 构造时自动调用一次；后续需手动调用 withTrashed / onlyTrashed 覆盖。
   */
  private function applySoftDeleteScope(): void
  {
    if ($this->usesSoftDelete() && $this->softDeleteActive) {
      $this->query->whereNull($this->tableName . '.' . $this->deleteTime);
    }
  }

  /** 获取主键字段名 */
  public function getPrimaryKey(): string
  {
    return $this->primaryKey;
  }

  /**
   * 获取底层 Query 构建器实例
   *
   * 用于 Relation 等组件获取 Query 来构建关联查询。
   *
   * @return Query
   */
  public function getQuery(): Query
  {
    return $this->query;
  }

  /**
   * 获取去前缀后的表名
   *
   * 用于自动推断关联外键时生成 `{表名}_{主键}` 格式的字段名。
   * 例如 prefix='ruyi_', tableName='ruyi_users' → 返回 'users'
   *
   * @return string
   */
  public function getTableBaseName(): string
  {
    $prefix = Table::getPrefix();
    if ($prefix && str_starts_with($this->tableName, $prefix)) {
      return substr($this->tableName, strlen($prefix));
    }
    return $this->tableName;
  }

  // ===================================================================
  // 关联关系
  // ===================================================================

  /**
   * 定义一对一关系
   *
   * 当前 Model 拥有一条关联 Model 记录。
   *
   * @param string $relatedClass 关联 Model 类名
   * @param string $foreignKey   关联表外键（可选，自动推断为 {当前表名}_{当前主键}）
   * @param string $localKey     当前表主键（可选，默认 $primaryKey）
   * @return HasOne
   *
   * @example
   * ```php
   * class User extends Model {
   *     public function profile() {
   *         return $this->hasOne(Profile::class);
   *         // 等价于 return $this->hasOne(Profile::class, 'user_id', 'id');
   *     }
   * }
   * $user = User::find(1);
   * echo $user->profile->bio;
   * ```
   */
  public function hasOne(string $relatedClass, string $foreignKey = '', string $localKey = ''): HasOne
  {
    return new HasOne($relatedClass, $this, $foreignKey, $localKey);
  }

  /**
   * 定义一对多关系
   *
   * 当前 Model 拥有多条关联 Model 记录。
   *
   * @param string $relatedClass 关联 Model 类名
   * @param string $foreignKey   关联表外键（可选，自动推断）
   * @param string $localKey     当前表主键（可选，默认 $primaryKey）
   * @return HasMany
   *
   * @example
   * ```php
   * class Post extends Model {
   *     public function comments() {
   *         return $this->hasMany(Comment::class);
   *     }
   * }
   * $post = Post::find(1);
   * foreach ($post->comments as $comment) { ... }
   * ```
   */
  public function hasMany(string $relatedClass, string $foreignKey = '', string $localKey = ''): HasMany
  {
    return new HasMany($relatedClass, $this, $foreignKey, $localKey);
  }

  /**
   * 定义反向一对多关系
   *
   * 当前 Model 属于一条父 Model 记录（即当前表持有外键）。
   *
   * @param string $relatedClass 关联的父 Model 类名
   * @param string $foreignKey   当前表外键（可选，自动推断为 {关联表名}_{关联表主键}）
   * @param string $localKey     关联表主键（可选，默认关联 Model 的 $primaryKey）
   * @return BelongsTo
   *
   * @example
   * ```php
   * class Comment extends Model {
   *     public function post() {
   *         return $this->belongsTo(Post::class);
   *         // 等价于 return $this->belongsTo(Post::class, 'post_id', 'id');
   *     }
   * }
   * $comment = Comment::find(1);
   * echo $comment->post->title;
   * ```
   */
  public function belongsTo(string $relatedClass, string $foreignKey = '', string $localKey = ''): BelongsTo
  {
    return new BelongsTo($relatedClass, $this, $foreignKey, $localKey);
  }

  /**
   * 获取指定关联的查询结果（带缓存）
   *
   * 若关联已加载则直接返回缓存，否则通过关系方法查询并缓存。
   *
   * @param string $relation 关系方法名
   * @return mixed
   */
  public function getRelation(string $relation): mixed
  {
    if (array_key_exists($relation, $this->relations)) {
      return $this->relations[$relation];
    }
    if (method_exists($this, $relation)) {
      $rel = $this->$relation();
      if ($rel instanceof Relation) {
        return $this->relations[$relation] = $rel->getResults();
      }
    }
    return null;
  }

  /**
   * 手动加载指定关联
   *
   * 适用于已有 Model 实例时按需加载关联数据。
   *
   * @param string ...$relations 关系方法名列表
   * @return $this
   *
   * @example
   * $user = User::find(1);
   * $user->load('profile', 'posts');
   */
  public function load(string ...$relations): static
  {
    foreach ($relations as $relation) {
      $this->getRelation($relation);
    }
    return $this;
  }

  /**
   * 设置需要预加载的关联关系（静态方法）
   *
   * 在 get/first/find 等终端方法调用前通过 with() 声明需要预加载的关联，
   * 终端方法执行时会自动进行批量 eager loading。
   *
   * @param string ...$relations 关系方法名列表
   * @return static
   *
   * @example
   * $users = User::with('profile')->where('status', 1)->get();
   * $post  = Post::with('comments', 'author')->first();
   */
  public static function with(string ...$relations): static
  {
    $instance = new static();
    $instance->eagerLoads = $relations;
    return $instance;
  }

  // ===================================================================
  // 数据导出
  // ===================================================================

  /**
   * 将当前行数据导出为关联数组
   *
   * $casts 中声明的字段通过 castFromDb 转为 PHP 输出格式；其余字段原样返回。
   */
  public function toArray(): array
  {
    $result = [];
    foreach ($this->data as $field => $value) {
      $type = $this->casts[$field] ?? null;
      $result[$field] = $type ? $this->castFromDb($type, $value) : $value;
    }
    foreach ($this->relations as $name => $value) {
      if ($value instanceof Model) {
        $result[$name] = $value->toArray();
      } elseif (is_array($value)) {
        $result[$name] = array_map(fn($item) => $item instanceof Model ? $item->toArray() : $item, $value);
      } else {
        $result[$name] = $value;
      }
    }
    return $result;
  }

  /**
   * 将当前行数据导出为 JSON 字符串
   *
   * @param int $flags JSON 编码选项（默认 JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES）
   */
  public function toJson(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
  {
    return json_encode($this->toArray(), $flags);
  }
}
