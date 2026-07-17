<?php

namespace kernel\Foundation\Database\PDO;

/**
 * 表字段定义
 *
 * 流式 API 定义单个字段的类型、约束等属性，最终生成字段 SQL。
 *
 * @example
 * // 基础用法
 * new Schema('id')->bigint()->unsigned()->autoIncrement()->comment('主键');
 * new Schema('name')->varchar(100)->nullable(false)->comment('用户名');
 * new Schema('created_at')->datetime()->default('CURRENT_TIMESTAMP');
 * new Schema('email')->varchar(128)->index()->comment('邮箱（普通索引）');
 * new Schema('code')->varchar(32)->unique()->comment('编码（唯一索引）');
 * new Schema('status')->tinyint()->index('idx_status')->comment('状态（自定义索引名）');
 *
 * // 配合 Table::$schema 使用，由 Table::create() 组装建表
 * class User extends Table {
 *     public $schema = [];
 *     function __construct() {
 *         parent::__construct('users');
 *         $this->schema = [
 *             new Schema('id')->bigint()->unsigned()->autoIncrement()->comment('主键'),
 *             new Schema('name')->varchar(100)->nullable(false)->comment('用户名'),
 *         ];
 *     }
 * }
 */
class Schema
{
  /**
   * 字段名
   * @var string
   */
  private $name;
  /**
   * 字段的 SQL 类型，如 INT、VARCHAR、TEXT 等
   * @var string
   */
  private $type = '';
  /**
   * 类型长度/宽度，如 INT(11) 中的 11
   * @var int|null
   */
  private $length = null;
  /**
   * DECIMAL 类型的总位数
   * @var int
   */
  private $precision = 10;
  /**
   * DECIMAL 类型的小数位数
   * @var int
   */
  private $scale = 2;
  /**
   * 是否允许 NULL，默认 true（允许）
   * @var bool
   */
  private $nullable = true;
  /**
   * 是否自增
   * @var bool
   */
  private $autoIncrement = false;
  /**
   * 默认值，未显式设置时为 null
   * @var mixed
   */
  private $default = null;
  /**
   * 是否显式调用过 default()，用于区分「DEFAULT NULL」与「不设 DEFAULT」
   * @var bool
   */
  private $hasDefault = false;
  /**
   * 字段注释/COMMENT
   * @var string
   */
  private $comment = '';
  /**
   * 是否无符号（仅对整数/浮点类型生效）
   * @var bool
   */
  private $unsigned = false;
  /**
   * 是否唯一索引
   * @var bool
   */
  private $unique = false;
  /**
   * 是否主键
   * @var bool
   */
  private $primary = false;
  /**
   * 是否普通索引
   * @var bool
   */
  private $index = false;
  /**
   * 索引名称，null 时自动生成为 idx_{字段名}
   * @var string|null
   */
  private $indexName = null;
  /**
   * ENUM 类型的可选值列表
   * @var array|null
   */
  private $values = null;

  /**
   * 构造
   *
   * @param string $name 字段名
   */
  function __construct($name)
  {
    $this->name = $name;
  }

  // ─── 类型方法 ──────────────────────────────────

  /**
   * BIGINT 大整数
   * @param int $length 显示宽度，默认 20
   * @return $this
   */
  function bigint($length = 20)   { $this->type = 'BIGINT';   $this->length = $length; return $this; }
  /**
   * INT 整数
   * @param int $length 显示宽度，默认 11
   * @return $this
   */
  function int($length = 11)      { $this->type = 'INT';      $this->length = $length; return $this; }
  /**
   * TINYINT 小整数
   * @param int $length 显示宽度，默认 4
   * @return $this
   */
  function tinyint($length = 4)   { $this->type = 'TINYINT';  $this->length = $length; return $this; }
  /**
   * SMALLINT 短整数
   * @param int $length 显示宽度，默认 6
   * @return $this
   */
  function smallint($length = 6)  { $this->type = 'SMALLINT'; $this->length = $length; return $this; }
  /**
   * MEDIUMINT 中等整数
   * @param int $length 显示宽度，默认 9
   * @return $this
   */
  function mediumint($length = 9) { $this->type = 'MEDIUMINT'; $this->length = $length; return $this; }
  /**
   * VARCHAR 变长字符串
   * @param int $length 最大长度，默认 255
   * @return $this
   */
  function varchar($length = 255) { $this->type = 'VARCHAR';  $this->length = $length; return $this; }
  /**
   * CHAR 定长字符串
   * @param int $length 长度，默认 1
   * @return $this
   */
  function char($length = 1)      { $this->type = 'CHAR';     $this->length = $length; return $this; }
  /**
   * TEXT 长文本（最大 64KB）
   * @return $this
   */
  function text()                 { $this->type = 'TEXT';                       return $this; }
  /**
   * MEDIUMTEXT 中等文本（最大 16MB）
   * @return $this
   */
  function mediumtext()           { $this->type = 'MEDIUMTEXT';                 return $this; }
  /**
   * LONGTEXT 超大文本（最大 4GB）
   * @return $this
   */
  function longtext()             { $this->type = 'LONGTEXT';                   return $this; }
  /**
   * DATETIME 日期时间
   * @return $this
   */
  function datetime()             { $this->type = 'DATETIME';                   return $this; }
  /**
   * TIMESTAMP 时间戳
   * @return $this
   */
  function timestamp()            { $this->type = 'TIMESTAMP';                  return $this; }
  /**
   * DATE 日期
   * @return $this
   */
  function date()                 { $this->type = 'DATE';                       return $this; }
  /**
   * TIME 时间
   * @return $this
   */
  function time()                 { $this->type = 'TIME';                       return $this; }
  /**
   * DECIMAL 定点数
   * @param int $precision 精度（总位数），默认 10
   * @param int $scale 小数位数，默认 2
   * @return $this
   */
  function decimal($precision = 10, $scale = 2) { $this->type = 'DECIMAL'; $this->precision = $precision; $this->scale = $scale; return $this; }
  /**
   * FLOAT 单精度浮点数
   * @return $this
   */
  function float()                { $this->type = 'FLOAT';                      return $this; }
  /**
   * DOUBLE 双精度浮点数
   * @return $this
   */
  function double()               { $this->type = 'DOUBLE';                     return $this; }
  /**
   * JSON 类型
   * @return $this
   */
  function json()                 { $this->type = 'JSON';                       return $this; }
  /**
   * 布尔类型（映射为 TINYINT(1)）
   * @return $this
   */
  function bool()                 { $this->type = 'TINYINT'; $this->length = 1; return $this; }
  /**
   * TIMESTAMP_MS 毫秒级时间戳（存储为 VARCHAR）
   * @param int $length 长度，默认 26（兼容 Y-m-d H:i:s.u）
   * @return $this
   */
  function timestamp_ms($length = 26) { $this->type = 'TIMESTAMP_MS'; $this->length = $length; return $this; }
  /**
   * BLOB 二进制大对象
   * @return $this
   */
  function blob()                 { $this->type = 'BLOB';                       return $this; }
  /**
   * ENUM 枚举类型
   * @param array $values 可选值列表
   * @return $this
   */
  function enum(array $values)    { $this->type = 'ENUM'; $this->values = $values; return $this; }

  // ─── 修饰符 ────────────────────────────────────

  /**
   * 自增（自动设置 unsigned）
   * @return $this
   */
  function autoIncrement() { $this->autoIncrement = true; $this->unsigned = true; return $this; }
  /**
   * 是否允许 NULL
   * @param bool $val true=允许（默认），false=NOT NULL
   * @return $this
   */
  function nullable($val = true)  { $this->nullable = $val; return $this; }
  /**
   * 默认值
   * @param mixed $value 支持字符串、数字、CURRENT_TIMESTAMP 等
   * @return $this
   */
  function default($value)  { $this->default = $value; $this->hasDefault = true; return $this; }
  /**
   * 字段注释
   * @param string $text
   * @return $this
   */
  function comment($text)   { $this->comment = $text; return $this; }
  /**
   * 无符号（仅整数/浮点类型有效）
   * @return $this
   */
  function unsigned()       { $this->unsigned = true; return $this; }
  /**
   * 唯一索引
   * @return $this
   */
  function unique()         { $this->unique = true; return $this; }
  /**
   * 主键
   * @return $this
   */
  function primary()        { $this->primary = true; return $this; }
  /**
   * 普通索引
   * @param string|null $name 索引名称，默认自动生成 idx_{字段名}
   * @return $this
   */
  function index($name = null) { $this->index = true; $this->indexName = $name; return $this; }

  // ─── 查询 ──────────────────────────────────────

  /**
   * 获取字段名
   * @return string
   */
  function getName()        { return $this->name; }
  /**
   * 是否自增
   * @return bool
   */
  function isAutoIncrement(){ return $this->autoIncrement; }
  /**
   * 是否主键
   * @return bool
   */
  function isPrimary()      { return $this->primary; }
  /**
   * 是否唯一索引
   * @return bool
   */
  function isUnique()       { return $this->unique; }
  /**
   * 是否普通索引
   * @return bool
   */
  function isIndex()        { return $this->index; }
  /**
   * 获取索引名称（未自定义时自动生成 idx_{字段名}）
   * @return string
   */
  function getIndexName()   { return $this->indexName ?: "idx_{$this->name}"; }
  /**
   * 获取字段对应的 PHP 类型
   *
   * 映射规则：
   * - JSON              → 'array'
   * - TINYINT(1)        → 'bool'
   * - INT/BIGINT 等     → 'int'
   * - FLOAT/DOUBLE      → 'float'
   * - DATETIME/TIMESTAMP(带毫秒精度) → 'timestamp_ms'
   * - DATETIME/TIMESTAMP             → 'timestamp'
   * - DATE              → 'date'
   * - 其余               → 'string'
   *
   * @return string 'int' | 'float' | 'bool' | 'string' | 'array' | 'timestamp' | 'timestamp_ms' | 'date'
   */
  function getPhpType()
  {
    if ($this->type === 'JSON') return 'array';
    if ($this->type === 'TIMESTAMP_MS') return 'timestamp_ms';
    if ($this->type === 'TINYINT' && $this->length === 1) return 'bool';
    if (in_array($this->type, ['INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'MEDIUMINT'])) return 'int';
    if (in_array($this->type, ['FLOAT', 'DOUBLE', 'DECIMAL'])) return 'float';
    if (in_array($this->type, ['DATETIME', 'TIMESTAMP'])) {
      return ($this->length >= 3) ? 'timestamp_ms' : 'timestamp';
    }
    if ($this->type === 'DATE') return 'date';
    return 'string';
  }

  // ─── 生成 ──────────────────────────────────────

  /**
   * 生成字段定义 SQL（单列）
   *
   * 例如：`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键'
   *
   * @return string
   */
  function toSQL()
  {
    $parts = ["`{$this->name}`"];

    // 类型 + 长度
    $typeSQL = $this->type;
    if ($this->type === 'TIMESTAMP_MS') {
      $typeSQL = 'VARCHAR';
    }
    if ($this->type === 'DECIMAL') {
      $typeSQL .= "({$this->precision},{$this->scale})";
    } elseif ($this->type === 'ENUM' && isset($this->values)) {
      $escaped = array_map(function ($v) { return "'{$v}'"; }, $this->values);
      $typeSQL .= "(" . implode(",", $escaped) . ")";
    } elseif ($this->length && in_array($this->type, ['INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'VARCHAR', 'CHAR', 'TIMESTAMP_MS'])) {
      $typeSQL .= "({$this->length})";
    }
    if ($this->unsigned && in_array($this->type, ['INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'FLOAT', 'DOUBLE', 'DECIMAL'])) {
      $typeSQL .= ' UNSIGNED';
    }
    $parts[] = $typeSQL;

    // NOT NULL / NULL
    if (!$this->nullable) {
      $parts[] = 'NOT NULL';
    }

    // AUTO_INCREMENT
    if ($this->autoIncrement) {
      $parts[] = 'AUTO_INCREMENT';
    }

    // DEFAULT
    if ($this->hasDefault) {
      $parts[] = 'DEFAULT ' . Statement::format($this->default, "'");
    }

    // COMMENT
    if ($this->comment) {
      $parts[] = 'COMMENT ' . Statement::format($this->comment, "'");
    }

    return implode(' ', $parts);
  }

  /**
   * 从 Schema 数组生成完整的 CREATE TABLE SQL
   *
   * 自动处理 PRIMARY KEY、UNIQUE KEY、INDEX，
   * 使用 InnoDB + utf8mb4。
   *
   * @param string $tableName 表名（含前缀）
   * @param Schema[] $columns 字段定义数组
   * @return string 完整建表 SQL
   */
  static function createTableSQL($tableName, $columns)
  {
    $colSQLs = [];
    $primaryKeys = [];
    $uniqueKeys = [];
    $indexKeys = [];

    foreach ($columns as $col) {
      if (!($col instanceof Schema)) continue;

      $colSQLs[] = $col->toSQL();

      if ($col->isPrimary() || $col->isAutoIncrement()) {
        $primaryKeys[] = $col->getName();
      }
      if ($col->isUnique() && !$col->isPrimary() && !$col->isAutoIncrement()) {
        $uniqueKeys[] = $col->getName();
      }
      if ($col->isIndex() && !$col->isPrimary() && !$col->isAutoIncrement() && !$col->isUnique()) {
        $indexKeys[] = $col;
      }
    }

    // PRIMARY KEY
    if ($primaryKeys) {
      $colSQLs[] = 'PRIMARY KEY (' . implode(', ', array_map(function ($k) { return "`{$k}`"; }, $primaryKeys)) . ')';
    }
    // UNIQUE KEY
    foreach ($uniqueKeys as $uk) {
      $colSQLs[] = "UNIQUE KEY `uk_{$uk}` (`{$uk}`)";
    }
    // INDEX KEY
    foreach ($indexKeys as $col) {
      $colSQLs[] = "INDEX `{$col->getIndexName()}` (`{$col->getName()}`)";
    }

    return "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n  " . implode(",\n  ", $colSQLs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
  }
}
