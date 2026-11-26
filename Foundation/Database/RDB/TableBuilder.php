<?php

namespace kernel\Foundation\Database\RDB;

use kernel\Foundation\Exception\Error;

/**
 * 表结构变更构建器
 *
 * 由 {@see Table::alter()} 创建，只负责生成并执行 ALTER TABLE，不参与 Model 的
 * schema 自动对比。列用 {@see ColumnDefinition} 声明，索引和字符集直接在表上声明。
 *
 * ```php
 * UserModel::alter()
 *   ->string('name', 64)->nullable()->change()
 *   ->index('idx_name_status', ['name', 'status'])
 *   ->charset('utf8mb4', 'utf8mb4_general_ci')
 *   ->sync();
 * ```
 */
class TableBuilder
{
  /**
   * 目标表
   *
   * @var Table
   */
  protected $table;
  /**
   * 列变更
   *
   * @var ColumnDefinition[]
   */
  protected $columns = [];
  /**
   * 要删除的列
   *
   * @var string[]
   */
  protected $dropColumns = [];
  /**
   * 要删除的索引
   *
   * @var string[]
   */
  protected $dropIndexes = [];
  /**
   * 要新增的索引
   *
   * @var array<string, array{unique:bool,columns:string[]}>
   */
  protected $indexes = [];
  /**
   * 目标字符集
   *
   * @var string|null
   */
  protected $charset = null;
  /**
   * 目标排序规则
   *
   * @var string|null
   */
  protected $collation = null;

  /**
   * @param Table $table 目标表
   */
  public function __construct(Table $table)
  {
    $this->table = $table;
  }

  // --------------------------------------------------------------------------
  // 列类型
  // --------------------------------------------------------------------------

  /**
   * 新增 BIGINT 列
   *
   * @param string $name 列名
   * @param int $length 显示宽度
   * @return ColumnDefinition
   */
  public function bigint($name, $length = 20)
  {
    return $this->addColumn((new Schema($name))->bigint($length));
  }

  /**
   * 新增 INT 列
   *
   * @param string $name 列名
   * @param int $length 显示宽度
   * @return ColumnDefinition
   */
  public function integer($name, $length = 11)
  {
    return $this->addColumn((new Schema($name))->int($length));
  }

  /**
   * 新增 TINYINT 列
   *
   * @param string $name 列名
   * @param int $length 显示宽度
   * @return ColumnDefinition
   */
  public function tinyint($name, $length = 4)
  {
    return $this->addColumn((new Schema($name))->tinyint($length));
  }

  /**
   * 新增 SMALLINT 列
   *
   * @param string $name 列名
   * @param int $length 显示宽度
   * @return ColumnDefinition
   */
  public function smallint($name, $length = 6)
  {
    return $this->addColumn((new Schema($name))->smallint($length));
  }

  /**
   * 新增 MEDIUMINT 列
   *
   * @param string $name 列名
   * @param int $length 显示宽度
   * @return ColumnDefinition
   */
  public function mediumint($name, $length = 9)
  {
    return $this->addColumn((new Schema($name))->mediumint($length));
  }

  /**
   * 新增 VARCHAR 列
   *
   * @param string $name 列名
   * @param int $length 最大长度
   * @return ColumnDefinition
   */
  public function string($name, $length = 255)
  {
    return $this->addColumn((new Schema($name))->varchar($length));
  }

  /**
   * string() 的别名
   *
   * @param string $name 列名
   * @param int $length 最大长度
   * @return ColumnDefinition
   */
  public function varchar($name, $length = 255)
  {
    return $this->string($name, $length);
  }

  /**
   * 新增 CHAR 列
   *
   * @param string $name 列名
   * @param int $length 长度
   * @return ColumnDefinition
   */
  public function char($name, $length = 1)
  {
    return $this->addColumn((new Schema($name))->char($length));
  }

  /**
   * 新增 TEXT 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function text($name)
  {
    return $this->addColumn((new Schema($name))->text());
  }

  /**
   * 新增 MEDIUMTEXT 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function mediumText($name)
  {
    return $this->addColumn((new Schema($name))->mediumtext());
  }

  /**
   * 新增 LONGTEXT 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function longText($name)
  {
    return $this->addColumn((new Schema($name))->longtext());
  }

  /**
   * 新增 DATETIME 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function datetime($name)
  {
    return $this->addColumn((new Schema($name))->datetime());
  }

  /**
   * 新增 TIMESTAMP 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function timestamp($name)
  {
    return $this->addColumn((new Schema($name))->timestamp());
  }

  /**
   * 新增 DATE 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function date($name)
  {
    return $this->addColumn((new Schema($name))->date());
  }

  /**
   * 新增 TIME 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function time($name)
  {
    return $this->addColumn((new Schema($name))->time());
  }

  /**
   * 新增 DECIMAL 列
   *
   * @param string $name 列名
   * @param int $precision 总位数
   * @param int $scale 小数位数
   * @return ColumnDefinition
   */
  public function decimal($name, $precision = 10, $scale = 2)
  {
    return $this->addColumn((new Schema($name))->decimal($precision, $scale));
  }

  /**
   * 新增 FLOAT 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function float($name)
  {
    return $this->addColumn((new Schema($name))->float());
  }

  /**
   * 新增 DOUBLE 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function double($name)
  {
    return $this->addColumn((new Schema($name))->double());
  }

  /**
   * 新增 JSON 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function json($name)
  {
    return $this->addColumn((new Schema($name))->json());
  }

  /**
   * 新增布尔列（TINYINT(1)）
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function boolean($name)
  {
    return $this->addColumn((new Schema($name))->bool());
  }

  /**
   * boolean() 的别名
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function bool($name)
  {
    return $this->boolean($name);
  }

  /**
   * 新增 ENUM 列
   *
   * @param string $name 列名
   * @param array $values 可选值
   * @return ColumnDefinition
   */
  public function enum($name, array $values)
  {
    return $this->addColumn((new Schema($name))->enum($values));
  }

  /**
   * 新增秒级时间戳列（INT UNSIGNED）
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function unixtime($name)
  {
    return $this->addColumn((new Schema($name))->unixtime());
  }

  /**
   * 新增毫秒级时间戳列（BIGINT UNSIGNED）
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function unixtimeMs($name)
  {
    return $this->addColumn((new Schema($name))->unixtime_ms());
  }

  /**
   * 新增毫秒级时间字符串列（VARCHAR）
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function timestampMs($name)
  {
    return $this->addColumn((new Schema($name))->timestamp_ms());
  }

  /**
   * 新增 BLOB 列
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function blob($name)
  {
    return $this->addColumn((new Schema($name))->blob());
  }

  /**
   * 新增自增大整数列
   *
   * AUTO_INCREMENT 列必须同时是主键或索引，通常配合 unique() / primary 使用。
   *
   * @param string $name 列名
   * @return ColumnDefinition
   */
  public function increments($name)
  {
    return $this->addColumn((new Schema($name))->bigint()->unsigned()->autoIncrement());
  }

  // --------------------------------------------------------------------------
  // 删除
  // --------------------------------------------------------------------------

  /**
   * 删除单列
   *
   * @param string $name 列名
   * @return $this
   */
  public function dropColumn($name)
  {
    return $this->dropColumns([$name]);
  }

  /**
   * 删除多列
   *
   * 可重复调用，自动去重；列不存在时执行阶段跳过。
   *
   * @param string[] $columns 列名
   * @return $this
   */
  public function dropColumns(array $columns)
  {
    foreach ($columns as $column) {
      $column = (string) $column;
      if ($column !== "" && !in_array($column, $this->dropColumns, true)) {
        $this->dropColumns[] = $column;
      }
    }

    return $this;
  }

  /**
   * 删除单个索引
   *
   * @param string $name 索引名
   * @return $this
   */
  public function dropIndex($name)
  {
    return $this->dropIndexes([$name]);
  }

  /**
   * 删除多个索引
   *
   * 可重复调用，自动去重；索引不存在时执行阶段跳过。
   *
   * @param string[] $indexes 索引名
   * @return $this
   */
  public function dropIndexes(array $indexes)
  {
    foreach ($indexes as $index) {
      $index = (string) $index;
      if ($index !== "" && !in_array($index, $this->dropIndexes, true)) {
        $this->dropIndexes[] = $index;
      }
    }

    return $this;
  }

  // --------------------------------------------------------------------------
  // 索引与表选项
  // --------------------------------------------------------------------------

  /**
   * 新增联合普通索引
   *
   * @param string $name 索引名
   * @param string[] $columns 索引列，按顺序生效
   * @return $this
   */
  public function index($name, array $columns)
  {
    return $this->addIndex($name, $columns, false);
  }

  /**
   * 新增联合唯一索引
   *
   * @param string $name 索引名
   * @param string[] $columns 索引列，按顺序生效
   * @return $this
   */
  public function unique($name, array $columns)
  {
    return $this->addIndex($name, $columns, true);
  }

  /**
   * 设置目标字符集
   *
   * 传入 collation 时一并转换排序规则；已有表执行 CONVERT TO CHARACTER SET。
   *
   * @param string $charset 字符集
   * @param string|null $collation 排序规则
   * @return $this
   */
  public function charset($charset, $collation = null)
  {
    $this->charset = (string) $charset;
    if ($collation !== null) {
      $this->collation = (string) $collation;
    }

    return $this;
  }

  /**
   * 设置目标排序规则
   *
   * 未设置 charset 时从排序规则前缀推导。
   *
   * @param string $collation 排序规则
   * @return $this
   */
  public function collation($collation)
  {
    $this->collation = (string) $collation;

    return $this;
  }

  // --------------------------------------------------------------------------
  // 执行
  // --------------------------------------------------------------------------

  /**
   * 是否有待执行的表结构变更
   *
   * create() / dropTable() 会立即执行，不计入待执行变更。
   *
   * @return bool
   */
  public function hasPendingChanges()
  {
    return !empty($this->columns)
      || !empty($this->dropColumns)
      || !empty($this->dropIndexes)
      || !empty($this->indexes)
      || ($this->charset !== null && $this->charset !== "")
      || ($this->collation !== null && $this->collation !== "");
  }

  /**
   * 按模型 schema 创建表
   *
   * @return bool
   */
  public function create()
  {
    if ($this->table->tableExists()) {
      throw new Error("表结构变更失败", 500, 500, "表 " . $this->table->tableName() . " 已存在");
    }

    return $this->table->create();
  }

  /**
   * 删除当前表
   *
   * @return bool
   */
  public function dropTable()
  {
    return $this->table->drop();
  }

  /**
   * 返回待执行 SQL
   *
   * 字符集转换单独一条，列 / 索引变更合并成一条 ALTER TABLE。
   *
   * @return string[]
   */
  public function sql()
  {
    if (!$this->table->tableExists()) {
      throw new Error("表结构变更失败", 500, 500, "表 " . $this->table->tableName() . " 不存在");
    }

    $actualColumns = $this->actualColumns();
    $actualIndexes = $this->actualIndexes();

    $declaredColumns = [];
    foreach ($this->columns as $column) {
      $name = $column->getName();
      if (isset($declaredColumns[$name])) {
        throw new Error("表结构变更失败", 500, 500, "列 {$name} 重复声明");
      }
      if (in_array($name, $this->dropColumns, true)) {
        throw new Error("表结构变更失败", 500, 500, "列 {$name} 同时声明删除和变更");
      }
      $declaredColumns[$name] = true;
    }

    $sqls = [];
    $charsetSQL = $this->charsetSQL();
    if ($charsetSQL) {
      $sqls[] = $charsetSQL;
    }

    $actions = [];
    foreach ($this->dropIndexes as $name) {
      if (isset($actualIndexes[$name])) {
        $actions[] = "DROP INDEX " . $this->quote($name);
      }
    }
    foreach ($this->dropColumns as $name) {
      if (isset($actualColumns[$name])) {
        $actions[] = "DROP COLUMN " . $this->quote($name);
      }
    }
    foreach ($this->columns as $column) {
      $actions[] = $this->columnSQL($column, $actualColumns);
    }
    foreach ($this->indexes as $name => $index) {
      if (isset($actualIndexes[$name]) && !in_array($name, $this->dropIndexes, true)) {
        throw new Error("表结构变更失败", 500, 500, "索引 {$name} 已存在");
      }
      $parts = array_map(function ($column) {
        return $this->quote($column);
      }, $index["columns"]);
      $actions[] = ($index["unique"] ? "ADD UNIQUE KEY" : "ADD KEY")
        . " " . $this->quote($name)
        . " (" . implode(", ", $parts) . ")";
    }

    if ($actions) {
      $sqls[] = "ALTER TABLE " . $this->quote($this->table->tableName()) . "\n  " . implode(",\n  ", $actions);
    }

    return $sqls;
  }

  /**
   * 执行表结构变更
   *
   * @return string[] 已执行的 SQL
   */
  public function sync()
  {
    $sqls = $this->sql();
    foreach ($sqls as $sql) {
      $this->table->exec($sql);
    }

    return $sqls;
  }

  /**
   * sync() 的别名
   *
   * @return string[]
   */
  public function apply()
  {
    return $this->sync();
  }

  // --------------------------------------------------------------------------
  // 内部
  // --------------------------------------------------------------------------

  /**
   * 记录列变更并返回列构建器
   *
   * @param Schema $schema 列定义
   * @return ColumnDefinition
   */
  private function addColumn(Schema $schema)
  {
    $column = new ColumnDefinition($this, $schema);
    $this->columns[] = $column;

    return $column;
  }

  /**
   * 校验并记录索引
   *
   * @param string $name 索引名
   * @param string[] $columns 索引列
   * @param bool $unique 是否唯一
   * @return $this
   */
  private function addIndex($name, array $columns, $unique)
  {
    $name = trim((string) $name);
    $columns = array_values(array_filter(array_map("strval", $columns), function ($column) {
      return $column !== "";
    }));
    if ($name === "" || empty($columns)) {
      throw new Error("表结构变更失败", 500, 500, "索引必须提供名称和非空列");
    }
    if (isset($this->indexes[$name])) {
      throw new Error("表结构变更失败", 500, 500, "索引 {$name} 重复声明");
    }

    $this->indexes[$name] = [
      "unique" => (bool) $unique,
      "columns" => $columns,
    ];

    return $this;
  }

  /**
   * 当前真实列，按字段名索引
   *
   * @return array<string, array>
   */
  private function actualColumns()
  {
    $columns = [];
    foreach ($this->table->getColumns() as $row) {
      $columns[$row["Field"]] = $row;
    }

    return $columns;
  }

  /**
   * 当前索引名集合
   *
   * @return array<string, true>
   */
  private function actualIndexes()
  {
    $indexes = [];
    foreach ($this->table->getIndexes() as $row) {
      $indexes[$row["Key_name"]] = true;
    }

    return $indexes;
  }

  /**
   * 生成单列的 ADD / MODIFY / CHANGE 动作
   *
   * @param ColumnDefinition $column 列变更
   * @param array<string, array> $actualColumns 当前真实列
   * @return string
   */
  private function columnSQL(ColumnDefinition $column, array $actualColumns)
  {
    $name = $column->getName();
    $operation = $column->getOperation();
    $definition = $column->getSchema()->definitionSQL();
    $position = $this->positionSQL($column);

    if ($operation === "add") {
      if (isset($actualColumns[$name])) {
        throw new Error("表结构变更失败", 500, 500, "列 {$name} 已存在，请改用 change()");
      }
      return "ADD COLUMN " . $this->quote($name) . " " . $definition . $position;
    }

    if ($operation === "modify") {
      if (!isset($actualColumns[$name])) {
        throw new Error("表结构变更失败", 500, 500, "列 {$name} 不存在，无法修改");
      }
      return "MODIFY COLUMN " . $this->quote($name) . " " . $definition . $position;
    }

    $newName = $column->getNewName();
    if ($newName === null || $newName === "") {
      throw new Error("表结构变更失败", 500, 500, "列 {$name} 缺少重命名目标");
    }
    if (!isset($actualColumns[$name])) {
      throw new Error("表结构变更失败", 500, 500, "列 {$name} 不存在，无法重命名");
    }
    if (isset($actualColumns[$newName])) {
      throw new Error("表结构变更失败", 500, 500, "列 {$newName} 已存在，无法重命名");
    }

    return "CHANGE COLUMN " . $this->quote($name) . " " . $this->quote($newName) . " " . $definition . $position;
  }

  /**
   * 生成 FIRST / AFTER 位置子句
   *
   * @param ColumnDefinition $column 列变更
   * @return string
   */
  private function positionSQL(ColumnDefinition $column)
  {
    if ($column->isFirst()) {
      return " FIRST";
    }
    if ($column->getAfter()) {
      return " AFTER " . $this->quote($column->getAfter());
    }

    return "";
  }

  /**
   * 生成字符集转换 SQL
   *
   * 目标字符集与当前一致且未指定不同 collation 时返回 null。
   *
   * @return string|null
   */
  private function charsetSQL()
  {
    $charset = trim((string) ($this->charset ?? ""));
    $collation = trim((string) ($this->collation ?? ""));
    if ($charset === "" && $collation === "") {
      return null;
    }

    $charset = $charset === "" ? null : strtolower($charset);
    $collation = $collation === "" ? null : strtolower($collation);
    if ($charset !== null) {
      $this->validateCharsetName($charset, "charset");
    }
    if ($collation !== null) {
      $this->validateCharsetName($collation, "collation");
      if ($charset === null) {
        $charset = $this->charsetFromCollation($collation);
      }
    }
    if ($charset === null) {
      throw new Error("表结构变更失败", 500, 500, "无法从 collation 推导 charset");
    }

    $status = $this->table->getStatus();
    $actualCollation = $status["Collation"] ?? null;
    $actualCharset = $actualCollation ? $this->charsetFromCollation((string) $actualCollation) : null;
    $charsetChanged = $actualCharset === null || strcasecmp($actualCharset, $charset) !== 0;
    $collationChanged = $collation !== null
      && ($actualCollation === null || strcasecmp((string) $actualCollation, $collation) !== 0);

    if (!$charsetChanged && !$collationChanged) {
      return null;
    }

    $sql = "ALTER TABLE " . $this->quote($this->table->tableName())
      . " CONVERT TO CHARACTER SET " . $charset;
    if ($collation !== null) {
      $sql .= " COLLATE " . $collation;
    }

    return $sql;
  }

  /**
   * 从排序规则推导字符集
   *
   * utf8mb4_general_ci → utf8mb4
   *
   * @param string $collation 排序规则
   * @return string|null
   */
  private function charsetFromCollation($collation)
  {
    $position = strpos((string) $collation, "_");

    return $position === false ? null : substr((string) $collation, 0, $position);
  }

  /**
   * 校验字符集 / 排序规则名称
   *
   * @param string $value 名称
   * @param string $label 用于错误信息
   * @return void
   */
  private function validateCharsetName($value, $label)
  {
    if (!preg_match("/^[A-Za-z0-9_]+$/", (string) $value)) {
      throw new Error("表结构变更失败", 500, 500, "{$label} 名称非法：{$value}");
    }
  }

  /**
   * 用反引号包裹标识符
   *
   * @param string $identifier 表名 / 列名 / 索引名
   * @return string
   */
  private function quote($identifier)
  {
    return "`" . str_replace("`", "``", (string) $identifier) . "`";
  }
}