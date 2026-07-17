<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Config;
use kernel\Foundation\Object\AbilityBaseObject;

class Table extends AbilityBaseObject
{
  /**
   * 数据表名称（含前缀）
   *
   * @var string
   */
  protected $tableName = "";

  /**
   * DB 类名（子类可覆盖实现多态切换）
   *
   * @var string
   */
  protected $DB = null;

  /**
   * 表前缀占位替换
   *
   * 键为占位符，值为替换值。在表名拼接前对前缀进行替换。
   *
   * @var array<string, string>
   */
  protected $prefixReplaces = [];

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

  /**
   * 配置前缀缓存
   *
   * @var string|null
   */
  private static $prefixCache = null;

  // ===================================================================
  // 构造
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

  // ===================================================================
  // 表名
  // ===================================================================

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
  // DDL
  // ===================================================================

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
    return $this->query($sql);
  }

  /**
   * 删除表
   *
   * @return bool
   */
  public function drop()
  {
    return $this->query("DROP TABLE IF EXISTS `{$this->tableName}`");
  }

  /**
   * 清空表（保留结构，重置自增）
   *
   * @return bool
   */
  public function truncate()
  {
    return $this->query("TRUNCATE TABLE `{$this->tableName}`");
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
    return $this->query("RENAME TABLE `{$this->tableName}` TO `{$newName}`");
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
    $this->query("CREATE TABLE `{$newName}` LIKE `{$this->tableName}`");

    if ($withData) {
      return $this->query("INSERT INTO `{$newName}` SELECT * FROM `{$this->tableName}`");
    }
    return true;
  }

  // ===================================================================
  // 信息查询
  // ===================================================================

  /**
   * 表是否存在
   *
   * @return bool
   */
  public function exists()
  {
    $result = $this->query("SHOW TABLES LIKE '{$this->tableName}'");
    return !empty($result);
  }

  /**
   * 获取建表 DDL
   *
   * @return string
   */
  public function getCreateSQL()
  {
    $result = $this->query("SHOW CREATE TABLE `{$this->tableName}`");
    return $result[0]['Create Table'] ?? '';
  }

  /**
   * 获取表字段结构
   *
   * @return array 每个字段的类型、是否可空、默认值、注释
   */
  public function getColumns()
  {
    return $this->query("SHOW FULL COLUMNS FROM `{$this->tableName}`");
  }

  /**
   * 获取表索引
   *
   * @return array 索引名称、字段、类型、基数
   */
  public function getIndexes()
  {
    return $this->all("SHOW INDEX FROM `{$this->tableName}`");
  }

  /**
   * 获取表状态信息
   *
   * @return array|null 引擎、行数、数据大小、自增值、创建时间
   */
  public function getStatus()
  {
    $result = $this->all("SHOW TABLE STATUS LIKE '{$this->tableName}'");
    return $result[0] ?? null;
  }

  /**
   * 优化表（整理碎片，回收空间）
   *
   * @return bool
   */
  public function optimize()
  {
    return $this->query("OPTIMIZE TABLE `{$this->tableName}`");
  }

  // ===================================================================
  // Schema 类型映射
  // ===================================================================

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

  /**
   * 执行 SQL（写操作）
   *
   * @param  string $sql
   * @return mixed
   */
  public function query($sql)
  {
    return $this->DB::query($sql);
  }

  /**
   * 执行 SQL（读操作，返回全部结果）
   *
   * @param  string $sql
   * @return array
   */
  public function all($sql)
  {
    return $this->DB::all($sql);
  }

  /**
   * 最后插入的自增 ID
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
  private static function getPrefix()
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
