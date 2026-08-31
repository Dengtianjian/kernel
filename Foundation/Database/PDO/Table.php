<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Config;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * DDL 与表管理基类
 *
 * Table 提供数据库表的 DDL 操作和表结构信息查询能力。它是 Model 的基类，
 * Model 在其上扩展了 ActiveRecord 操作 + 类型转换 + 软删除等特性。
 *
 * ## 职责范围
 *
 * - **DDL 操作**：`create()` / `drop()` / `truncate()` / `rename()` / `copy()`（统一返回 bool）
 * - **信息查询**：`tableExists()` / `getCreateSQL()` / `getColumns()` / `getIndexes()` / `getStatus()` / `optimize()`
 * - **表名管理**：`prefix()` 自动添加配置前缀，`prefixReplaces` 支持前缀占位替换
 * - **Schema 映射**：`getPhpSchema()` 将 `$schema` 中的 Schema 定义转换为字段→PHP类型映射
 * - **SQL 执行**：`exec()` / `execQuery()` 底层执行；`select()` / `selectOne()` / `scalar()` 便捷查询
 *
 * ## 代码组织（自上而下）
 *
 *   属性（表基本信息 / 表前缀配置 / 表结构定义）
 *   → 构造与表名 → DDL → 信息查询 → Schema 类型映射 → SQL 执行 → 内部方法
 *
 * ## exec 与 select 的分工
 *
 * | 方法 | 底层 | 结果集 | 适用 |
 * |------|------|--------|------|
 * | `exec($sql)` | `PDO::exec` | 否，返回行数 | INSERT/UPDATE/DELETE/DDL |
 * | `execQuery($sql)` | `PDO::query` | 是，PDOStatement | 需自行控制取数 |
 * | `select($sql, $bindings)` | `PDO::query` + fetchAll | 是，array | 读多行（推荐） |
 *
 * `SHOW` / `OPTIMIZE TABLE` 返回的是结果集，故信息查询方法一律走 `select()`。
 *
 * ## 表前缀机制
 *
 * Table 自动从 `Config::get('database/mysql/prefix')` 读取前缀并添加到表名前。
 * 子类可通过 `$prefixReplaces` 属性对前缀中的占位符做动态替换。
 *
 * ## 继承关系
 *
 * ```
 * Table (DDL + 信息查询)
 *   └── Model (ActiveRecord + casts + 软删除)
 * ```
 *
 * @see Model  子类，提供 ActiveRecord 能力
 * @see Schema 字段定义类，配合 $this->schema 定义表结构
 */
class Table extends AbilityBaseObject
{
  // ===================================================================
  // 属性：表基本信息
  // ===================================================================

  /**
   * 数据表名称（含前缀）
   *
   * 构造时自动经 prefix() 补上配置前缀，后续所有 DDL / 信息查询都基于该值。
   *
   * @var string
   */
  protected $tableName = "";

  /**
   * DB 类名（子类可覆盖实现多态切换）
   *
   * 所有 SQL 执行都通过 `$this->DB::xxx()` 转发，覆盖此属性即可整体切换
   * 到另一个 DB 门面类（如读写分离场景切到从库门面）。
   *
   * @var string
   */
  protected $DB = null;

  // ===================================================================
  // 属性：表前缀配置
  // ===================================================================

  /**
   * 表前缀占位替换
   *
   * 键为占位符，值为替换值。在表名拼接前对前缀进行替换。
   *
   * @var array<string, string>
   */
  protected $prefixReplaces = [];

  /**
   * 配置前缀缓存
   *
   * 首次调用 getPrefix() 时从配置读取并缓存，避免重复读取配置。
   *
   * @var string|null
   */
  private static $prefixCache = null;

  // ===================================================================
  // 属性：表结构定义
  // ===================================================================

  /**
   * 表结构定义（DDL 用）
   *
   * 存放 Schema 对象数组，用于 create() 生成建表 SQL。
   * 子类覆盖示例：
   *
   *   public $schema = [
   *       new Schema('id')->bigint()->unsigned()->autoIncrement()->comment('主键'),
   *       new Schema('name')->varchar(100)->nullable(false)->comment('名称'),
   *   ];
   *
   * 注意：CRUD 读写时的类型转换请使用 Model::$casts。
   *
   * @var Schema[]
   */
  public $schema = [];

  // ===================================================================
  // 构造与表名
  // ===================================================================

  /**
   * @param string|null $tableName 数据表名称
   */
  public function __construct($tableName = null)
  {
    if ($tableName) {
      $this->tableName = $tableName;
    }
    $this->tableName = $this->prefix($this->tableName);

    $this->DB = DB::class;
  }

  /**
   * 表名添加前缀
   *
   * @param  string $tableName
   * @return string
   */
  public function prefix($tableName)
  {
    $prefix = self::getPrefix();
    if (!$prefix) {
      return $tableName;
    }

    if ($this->prefixReplaces) {
      $prefix = str_replace(
        array_keys($this->prefixReplaces),
        array_values($this->prefixReplaces),
        $prefix
      );
    }

    return "{$prefix}_{$tableName}";
  }

  /**
   * 获取当前表名
   *
   * @return string
   */
  public function tableName()
  {
    return $this->tableName;
  }

  // ===================================================================
  // DDL（表结构操作）
  // ===================================================================
  //
  // 全部统一返回 bool。
  //
  // 注意：PDO::exec 对 DDL 成功时返回 0，而 PHP 中 0 为 falsy，
  // 因此内部统一用 `!== false` 判定，调用方可直接 if ($table->drop())。
  //

  /**
   * 创建表
   *
   * 根据 $this->schema 生成 CREATE TABLE SQL 并执行。
   *
   * @return bool
   */
  public function create()
  {
    if (empty($this->schema)) {
      return true;
    }

    $sql = Schema::createTableSQL($this->tableName, $this->schema);

    // 见本分区说明：DDL 成功返回 0，需与 false 严格比较
    return $this->exec($sql) !== false;
  }

  /**
   * 删除表
   *
   * @return bool
   */
  public function drop()
  {
    return $this->exec("DROP TABLE IF EXISTS `{$this->tableName}`") !== false;
  }

  /**
   * 清空表（保留结构，重置自增）
   *
   * @return bool
   */
  public function truncate()
  {
    return $this->exec("TRUNCATE TABLE `{$this->tableName}`") !== false;
  }

  /**
   * 重命名表
   *
   * @param  string $newName 新表名（不含前缀，自动补充）
   * @return bool
   */
  public function rename($newName)
  {
    $newName = $this->prefix($newName);

    return $this->exec("RENAME TABLE `{$this->tableName}` TO `{$newName}`") !== false;
  }

  /**
   * 复制表
   *
   * @param  string $newName  新表名（不含前缀）
   * @param  bool   $withData 是否连带数据，默认仅复制结构
   * @return bool
   */
  public function copy($newName, $withData = false)
  {
    $newName = $this->prefix($newName);

    // 建表失败时直接返回 false，不必再尝试复制数据
    if ($this->exec("CREATE TABLE `{$newName}` LIKE `{$this->tableName}`") === false) {
      return false;
    }

    if ($withData) {
      // INSERT 是写操作，PDO::exec 返回受影响行数（0 表示无数据可复制，仍属成功）
      return $this->exec("INSERT INTO `{$newName}` SELECT * FROM `{$this->tableName}`") !== false;
    }

    return true;
  }

  // ===================================================================
  // 信息查询（表元数据）
  // ===================================================================
  //
  // 这些语句（SHOW / OPTIMIZE TABLE）返回的都是**结果集**，
  // 因此内部统一走 select()，不能用 exec()（PDO::exec 不产生结果集）。
  //

  /**
   * 表是否存在
   *
   * @return bool
   */
  public function tableExists()
  {
    $result = $this->select("SHOW TABLES LIKE '{$this->tableName}'");
    return !empty($result);
  }

  /**
   * 获取建表 DDL
   *
   * @return string
   */
  public function getCreateSQL()
  {
    $result = $this->select("SHOW CREATE TABLE `{$this->tableName}`");
    return $result[0]['Create Table'] ?? '';
  }

  /**
   * 获取表字段结构
   *
   * @return array 每个字段的类型、是否可空、默认值、注释
   */
  public function getColumns()
  {
    return $this->select("SHOW FULL COLUMNS FROM `{$this->tableName}`");
  }

  /**
   * 获取表索引
   *
   * @return array 索引名称、字段、类型、基数
   */
  public function getIndexes()
  {
    return $this->select("SHOW INDEX FROM `{$this->tableName}`");
  }

  /**
   * 获取表状态信息
   *
   * @return array|null 引擎、行数、数据大小、自增值、创建时间
   */
  public function getStatus()
  {
    $result = $this->select("SHOW TABLE STATUS LIKE '{$this->tableName}'");
    return $result[0] ?? null;
  }

  /**
   * 优化表（整理碎片，回收空间）
   *
   * MySQL 的 OPTIMIZE TABLE 会**返回结果集**（含 Table / Op / Msg_type / Msg_text 四列），
   * 而不是受影响行数，因此这里用 select() 取回结果，并逐行检查 Msg_type 是否出错。
   *
   * @return bool 全部表均报告 status/ok/note 时返回 true；任一报 error/warning 返回 false
   */
  public function optimize()
  {
    $result = $this->select("OPTIMIZE TABLE `{$this->tableName}`");

    // 结果集为空说明语句本身没跑起来
    if (empty($result)) {
      return false;
    }

    foreach ($result as $row) {
      $msgType = strtolower($row['Msg_type'] ?? '');
      if ($msgType === 'error' || $msgType === 'warning') {
        return false;
      }
    }

    return true;
  }

  // ===================================================================
  // Schema 类型映射
  // ===================================================================
  //
  // 将 $schema 中的 Schema 对象映射为「字段名 → PHP 类型」，
  // 供 Model 推导出 $schemaCasts（CRUD 读写的类型转换依据）。
  //

  /**
   * 将 $schema 中的 Schema 对象转换为字段 → PHP 类型映射
   *
   * @example
   * // 返回 ['id' => 'int', 'name' => 'string']
   *
   * @return array<string, string>
   */
  public function getPhpSchema()
  {
    if (empty($this->schema)) {
      return [];
    }

    $map = [];
    foreach ($this->schema as $col) {
      if ($col instanceof Schema) {
        $map[$col->getName()] = $col->getPhpType();
      }
    }
    return $map;
  }

  // ===================================================================
  // SQL 执行
  // ===================================================================
  //
  // 两个底层方法：
  //   exec($sql)      —— 走 PDO::exec，**不产生结果集**，返回受影响行数。
  //                      注意：DDL 成功时返回 0（PHP 中为 falsy），
  //                      故 create/drop/truncate/rename/copy 均用 !== false 转为 bool。
  //   execQuery($sql) —— 走 PDO::query，**返回结果集**（PDOStatement），需自行 fetch。
  //
  // 四个便捷方法（推荐日常使用，支持参数绑定）：
  //   select($sql, $bindings)     读多行 → array
  //   selectOne($sql, $bindings)  读单行 → array|null
  //   scalar($sql, $bindings)     读标量 → mixed
  //   insertId()                  取最后插入的自增 ID
  //

  /**
   * 执行原生 SQL（写操作 / DDL）
   *
   * 底层走 PDO::exec，不返回结果集。适用于 INSERT / UPDATE / DELETE / DDL。
   *
   * @param  string $sql 原生 SQL（不支持参数绑定，需自行转义）
   * @return int|false 受影响行数；DDL 成功通常返回 0，失败返回 false
   *
   * @see select() 读操作请用这个
   */
  public function exec($sql)
  {
    return $this->DB::exec($sql);
  }

  /**
   * 执行原生 SQL（通用，返回结果集对象）
   *
   * 底层走 PDO::query，返回 PDOStatement，调用方需自行 fetch。
   * 仅当需要自行控制取数方式时使用；绝大多数场景请用 select() / selectOne()。
   *
   * @param  string $sql 原生 SQL
   * @return \PDOStatement|false 失败返回 false
   */
  public function execQuery($sql)
  {
    return $this->DB::query($sql);
  }

  /**
   * 执行原生 SQL 并取回全部结果行
   *
   * @param  string $sql      原生 SQL，可用 `?` 或 `:name` 占位符
   * @param  array  $bindings 绑定参数（预处理，防 SQL 注入）
   * @return array 二维数组，每行一个关联数组；无结果返回 []
   *
   * @example
   * $table->select('SELECT * FROM users WHERE uid = ?', [1]);
   */
  public function select($sql, $bindings = [])
  {
    return $this->DB::select($sql, $bindings);
  }

  /**
   * 执行原生 SQL 并取回第一行
   *
   * @param  string $sql      原生 SQL
   * @param  array  $bindings 绑定参数
   * @return array|null 单行关联数组；无结果返回 null
   */
  public function selectOne($sql, $bindings = [])
  {
    return $this->DB::selectOne($sql, $bindings);
  }

  /**
   * 执行原生 SQL 并取回单个标量值
   *
   * 适用于 COUNT / MAX / SUM 等单值查询。
   *
   * @param  string $sql      原生 SQL
   * @param  array  $bindings 绑定参数
   * @return mixed 首行首列的值；无结果返回 null
   *
   * @example
   * $total = $table->scalar('SELECT COUNT(*) FROM users WHERE uid = ?', [1]);
   */
  public function scalar($sql, $bindings = [])
  {
    return $this->DB::scalar($sql, $bindings);
  }

  /**
   * 获取最后插入的自增 ID
   *
   * @return int
   */
  public function insertId()
  {
    return $this->DB::insertId();
  }

  // ===================================================================
  // 内部
  // ===================================================================

  /**
   * 获取配置中的表前缀（静态缓存）
   *
   * @return string
   */
  protected static function getPrefix()
  {
    if (self::$prefixCache !== null) {
      return self::$prefixCache;
    }

    $prefix = Config::get("database/mysql/prefix");
    if (is_array($prefix)) {
      $prefix = join("_", $prefix);
    }

    self::$prefixCache = (string) $prefix;
    return self::$prefixCache;
  }
}
