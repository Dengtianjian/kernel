<?php

namespace kernel\Modules\DiscuzX\Foundation\Database;

use kernel\Foundation\Database\PDO\Query;
use kernel\Foundation\Database\PDO\Statement;

/**
 * DiscuzX 查询构建器
 *
 * kernel 的 `Query` 通过 PDO 驱动执行 SQL；而 DiscuzX 环境下数据库由 Discuz 自己连接，
 * kernel 的 PDO 驱动并不存在（`Connections` 中没有注册任何 driver，直接构造 Query 会抛错）。
 *
 * 因此本类做两件事：
 * 1. **只构建 SQL**：复用 kernel `Query` 的链式 API 与 `Statement` 生成器（不依赖任何 PDO 驱动）；
 * 2. **执行全部交给 Discuz**：`\DB::query()` / `fetch_all()` / `fetch_first()` / `result_first()` / `insert_id()`。
 *
 * 同时补齐 DiscuzX 模型层用到的别名方法：`getAll` / `getOne` / `exist` / `batchUpdate` /
 * `increment` / `decrement`，并保证每次执行后重置查询状态，避免同一实例上条件累积。
 *
 * 表名处理是**幂等**的：既支持未加前缀的表名（自动补 `\DB::table()`），
 * 也支持已经带前缀的完整表名（原样使用）。
 *
 * 注意：Discuz 的 DB 层没有预处理语句，`Query` 内部若产生命名占位符（如 `:__in_0`），
 * 会在执行前用格式化后的字面量替换掉（`resolveSQL()`）。
 */
class DiscuzXQuery extends Query
{
  /**
   * 调试模式：为 true 时执行方法只返回 SQL 字符串，不真正执行
   *
   * @var bool
   */
  protected $dryRun = false;

  /**
   * 是否已显式设置过 SELECT 字段
   *
   * kernel `Query` 的 select 字段是私有属性、且 `addSelect()` 空参调用会报错，
   * 这里自行记录状态，用于在「未设置字段」时安全地补一个 `*`（同时把 executeType 置为 select）。
   *
   * @var bool
   */
  protected $hasSelect = false;

  /**
   * @param string|null $tableName 表名（可不含前缀）
   */
  function __construct($tableName = null)
  {
    // 刻意不调用 parent::__construct()：它内部会向 Connections 索取 PDO 驱动，
    // 而 DiscuzX 下内核并未注册 PDO 驱动，会抛 "切换回默认数据库失败"。
    // reset() 是 public 方法，可在子类中安全调用，用于初始化 Query 的私有查询状态
    // （options / sql / bindings）。
    $this->reset();

    if ($tableName) {
      $this->from(self::realTableName($tableName));
    }
  }

  /**
   * 读取或设置 dryRun（调试用：只出 SQL 不执行）
   *
   * @param bool|null $val
   * @return bool|static
   */
  function dryRun($val = null)
  {
    if (!is_null($val)) {
      $this->dryRun = (bool) $val;
      return $this;
    }
    return $this->dryRun;
  }

  /* ==================== SELECT 字段（记录状态） ==================== */

  /**
   * @param mixed ...$column
   * @return static
   */
  function select(...$column)
  {
    parent::select(...$column);
    $this->hasSelect = true;
    return $this;
  }

  /**
   * @param mixed ...$column
   * @return static
   */
  function addSelect(...$column)
  {
    parent::addSelect(...$column);
    if (!empty($column)) {
      $this->hasSelect = true;
    }
    return $this;
  }

  /**
   * @param string $columnSQL
   * @return static
   */
  function selectRaw($columnSQL)
  {
    parent::selectRaw($columnSQL);
    $this->hasSelect = true;
    return $this;
  }

  /**
   * @param mixed ...$columns
   * @return static
   */
  function distinct(...$columns)
  {
    parent::distinct(...$columns);
    $this->hasSelect = true;
    return $this;
  }

  /**
   * 补全 Discuz 表前缀（幂等）
   *
   * @param string $tableName
   * @return string
   */
  static function realTableName($tableName)
  {
    $tableName = (string) $tableName;
    if ($tableName === "") {
      return $tableName;
    }

    $prefix = (string) \DB::table("");
    if ($prefix !== "" && strpos($tableName, $prefix) === 0) {
      return $tableName;
    }

    return \DB::table($tableName);
  }

  /* ==================== 读 ==================== */

  /**
   * 查询多行
   *
   * @param array $params 预留（Discuz 无预处理）
   * @return array|string dryRun 时返回 SQL
   */
  function get($params = [])
  {
    $sql = $this->selectSQL();
    $this->afterExecute();
    if ($this->dryRun) {
      return $sql;
    }
    return DiscuzXDB::fetchAll($sql);
  }

  /**
   * 查询多行（DiscuzX 别名）
   *
   * @param array $params
   * @return array|string
   */
  function getAll($params = [])
  {
    return $this->get($params);
  }

  /**
   * 查询第一行
   *
   * @param array $params
   * @return array|null|string 无结果返回 null
   */
  function first($params = [])
  {
    $this->limit(1);
    $sql = $this->selectSQL();
    $this->afterExecute();
    if ($this->dryRun) {
      return $sql;
    }
    return DiscuzXDB::fetchFirst($sql);
  }

  /**
   * 查询第一行（DiscuzX 别名）
   *
   * @param array $params
   * @return array|null|string
   */
  function getOne($params = [])
  {
    return $this->first($params);
  }

  /**
   * 查询一列的值
   *
   * @param string $column
   * @param array  $params
   * @return mixed
   */
  function value($column, $params = [])
  {
    $row = $this->first($params);
    return is_array($row) ? ($row[$column] ?? null) : null;
  }

  /**
   * 查询一列的值集合
   *
   * @param string      $column
   * @param string|null $indexKey
   * @param array       $params
   * @return array
   */
  function pluck($column, $indexKey = null, $params = [])
  {
    $rows = $this->get($params);
    if (!is_array($rows)) {
      return [];
    }

    $result = [];
    foreach ($rows as $row) {
      if ($indexKey === null) {
        $result[] = $row[$column] ?? null;
      } else {
        $result[$row[$indexKey] ?? ""] = $row[$column] ?? null;
      }
    }
    return $result;
  }

  /* ==================== 聚合 ==================== */

  /**
   * 统计记录数
   *
   * @param string $column 列名，支持 "*"、"DISTINCT xxx"
   * @param array  $params
   * @return int|string dryRun 时返回 SQL
   */
  function count($column = "*", $params = [])
  {
    return $this->aggregate("COUNT", $column);
  }

  /**
   * 最大值
   */
  function max($column, $params = [])
  {
    return $this->aggregate("MAX", $column);
  }

  /**
   * 最小值
   */
  function min($column, $params = [])
  {
    return $this->aggregate("MIN", $column);
  }

  /**
   * 平均值
   */
  function avg($column, $params = [])
  {
    return $this->aggregate("AVG", $column);
  }

  /**
   * 求和
   */
  function sum($column, $params = [])
  {
    return $this->aggregate("SUM", $column);
  }

  /**
   * 是否存在满足条件的记录
   *
   * @param array $params
   * @return bool
   */
  function exists($params = [])
  {
    return (int) $this->count() > 0;
  }

  /**
   * 是否存在（DiscuzX 别名，旧代码使用单数 exist）
   *
   * @param array $params
   * @return bool
   */
  function exist($params = [])
  {
    return $this->exists($params);
  }

  /* ==================== 写 ==================== */

  /**
   * 插入
   *
   * @param array $data
   * @param bool  $isReplaceInto
   * @param bool  $isIgnore
   * @param bool  $returnId 是否返回自增 ID
   * @param array $params
   * @return bool|int|string dryRun 时返回 SQL
   */
  function insert($data, $isReplaceInto = false, $isIgnore = false, $returnId = false, $params = [])
  {
    $sql = $this->writeSql($isReplaceInto ? "replace" : "insert", $data, ["insertIsIgnore" => $isIgnore]);
    $this->afterExecute();
    if ($this->dryRun) {
      return $sql;
    }

    \DB::query($sql);
    return $returnId ? DiscuzXDB::insertId() : true;
  }

  /**
   * 插入并返回自增 ID
   *
   * @return int|string
   */
  function insertGetId($data, $isReplaceInto = false, $isIgnore = false, $params = [])
  {
    return $this->insert($data, $isReplaceInto, $isIgnore, true, $params);
  }

  /**
   * 更新（需先通过 where() 指定条件，否则会更新全表）
   *
   * @param array $data
   * @param array $params
   * @return bool|string dryRun 时返回 SQL
   */
  function update($data, $params = [])
  {
    $sql = $this->writeSql("update", $data);
    $this->afterExecute();
    if ($this->dryRun) {
      return $sql;
    }

    \DB::query($sql);
    return true;
  }

  /**
   * 删除（需先通过 where() 指定条件，否则会清空全表）
   *
   * @param array $params
   * @return bool|string dryRun 时返回 SQL
   */
  function delete($params = [])
  {
    $sql = $this->writeSql("delete");
    $this->afterExecute();
    if ($this->dryRun) {
      return $sql;
    }

    \DB::query($sql);
    return true;
  }

  /**
   * 批量更新（REPLACE INTO 语义）
   *
   * 注意：Discuz 的 REPLACE INTO 是"先删后插"，未提供的列会丢失，
   * 仅适用于已知完整行数据的场景。
   *
   * @param array $fieldNames 字段名列表
   * @param array $values     二维数组，每行按 $fieldNames 顺序排列
   * @return bool|string
   */
  function batchUpdate($fieldNames, $values)
  {
    $sql = Statement::batchUpdate($this->getTableName(), $fieldNames, $values);
    $this->afterExecute();
    if ($this->dryRun) {
      return $sql;
    }

    \DB::query($sql);
    return true;
  }

  /**
   * 字段自增
   *
   * @param string $field
   * @param int    $value
   * @return bool|string
   */
  function increment($field, $value = 1)
  {
    return $this->update([
      $field => $this->raw("`" . self::safeIdentifier($field) . "` + " . intval($value)),
    ]);
  }

  /**
   * 字段自减
   *
   * @param string $field
   * @param int    $value
   * @return bool|string
   */
  function decrement($field, $value = 1)
  {
    return $this->update([
      $field => $this->raw("`" . self::safeIdentifier($field) . "` - " . intval($value)),
    ]);
  }

  /* ==================== 内部 ==================== */

  /**
   * 生成 SELECT 语句（自动补 executeType=select）
   *
   * @return string
   */
  protected function selectSQL()
  {
    // 未显式设置字段时补一个 "*"：既把 executeType 置为 select，
    // 又避免 kernel `addSelect()` 空参调用报错（其内部 array_push 需要至少一个值）
    if (!$this->hasSelect) {
      $this->selectRaw("*");
    }
    return $this->resolveSQL($this->getSQL());
  }

  /**
   * 聚合查询
   *
   * @param string $func
   * @param string $column
   * @return mixed|string
   */
  protected function aggregate($func, $column)
  {
    // clone 会连同 Query 的私有状态一起复制，可安全地在副本上改写 SELECT 字段
    $query = clone $this;
    $query->select($query->raw("{$func}(" . self::safeColumn($column) . ")"));

    $sql = $this->resolveSQL($query->getSQL());
    $this->afterExecute();
    if ($this->dryRun) {
      return $sql;
    }

    $value = DiscuzXDB::resultFirst($sql);
    return $value === null ? 0 : $value;
  }

  /**
   * 执行后重置查询状态，避免同一实例重复使用时条件累积
   *
   * @return void
   */
  protected function afterExecute()
  {
    $this->reset();
    $this->hasSelect = false;
  }

  /**
   * 把残留的命名占位符替换为字面量（Discuz 的 DB 层没有预处理语句）
   *
   * @param string $sql
   * @return string
   */
  protected function resolveSQL($sql)
  {
    $bindings = $this->getBindings();
    if (empty($bindings) || strpos($sql, ":") === false) {
      return $sql;
    }

    foreach ($bindings as $key => $value) {
      $sql = str_replace($key, self::formatValue($value), $sql);
    }
    return $sql;
  }

  /**
   * 将 PHP 值格式化为可内联进 SQL 的字面量
   *
   * @param mixed $value
   * @return string
   */
  static function formatValue($value)
  {
    if (is_null($value)) {
      return "NULL";
    }
    if (is_bool($value)) {
      return $value ? "1" : "0";
    }
    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }
    if (is_array($value)) {
      return "(" . implode(", ", array_map([__CLASS__, "formatValue"], $value)) . ")";
    }
    return "'" . addslashes((string) $value) . "'";
  }

  /**
   * 列名白名单校验（用于聚合函数等无法批处理的场景）
   *
   * @param string $column
   * @return string
   */
  static function safeColumn($column)
  {
    $column = (string) $column;
    if ($column === "") {
      return "*";
    }
    return preg_match('/^[A-Za-z0-9_.*`\s]+$/', $column) ? $column : "*";
  }

  /**
   * 标识符清理（用于自增/自减拼接）
   *
   * @param string $identifier
   * @return string
   */
  static function safeIdentifier($identifier)
  {
    return preg_replace('/[^A-Za-z0-9_]/', '', (string) $identifier);
  }
}
