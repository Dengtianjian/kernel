<?php

namespace kernel\Foundation\Database\SQLite;

use kernel\Foundation\BaseObject;
use kernel\Foundation\Database\PDO\Query;

class SQLiteModel extends BaseObject
{
  /**
   * 表名称
   *
   * @var string
   */
  protected $tableName = "";
  /**
   * 表文件名，包含路径。相对于F_APP_ROOT的路径地址
   *
   * @var string
   */
  protected $tableFileName = "";

  /**
   * SQLite实例
   *
   * @var SQLite
   */
  protected $SQLite = null;
  protected $query;
  protected $returnSql = false;
  protected $DB = null;
  /**
   * 实例化模型
   *
   * @param int $flags 可选的 flag，用于确定如何打开 SQLite 数据库。默认使用 SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE 打开。  
   * - SQLITE3_OPEN_READONLY：以只读方式打开数据库。  
   * - SQLITE3_OPEN_READWRITE：以读写方式打开数据库。  
   * - SQLITE3_OPEN_CREATE：如果数据库不存在，则创建数据库。  
   * @param string $encryptionKey 加密和解密 SQLite 数据库时使用的可选加密密钥。如果未安装 SQLite 加密模块，则此参数无效。
   */
  public function __construct($flags = SQLITE3_OPEN_READWRITE, $encryptionKey = null)
  {
    if (!$this->SQLite) {
      $this->query = new Query($this->tableName);
      $this->SQLite = new SQLite($this->tableFileName, $flags, $encryptionKey);
    }
  }
  function order($field, $by = "ASC")
  {
    $this->query->order($field, $by);
    return $this;
  }
  function field(...$fieldNames)
  {
    $this->query->field($fieldNames);
    return $this;
  }
  function distinct($fieldName)
  {
    $this->query->distinct($fieldName);
    return $this;
  }
  function groupBy($fieldName)
  {
    $this->query->groupBy($fieldName);
    return $this;
  }
  function limit($startOrNumber, $number = null)
  {
    $this->query->limit($startOrNumber, $number);
    return $this;
  }
  function page($pages, $perPage = 10)
  {
    if ($pages === 0 && $perPage === 0) {
      return $this;
    }
    $this->query->page($pages, $perPage);
    return $this;
  }
  function cancelPage()
  {
    $this->query->clearPage();
    return $this;
  }
  function skip($number)
  {
    $this->query->skip($number);
    return $this;
  }
  function where($fieldNameOrFieldValue, $value = null, $glue = "=", $operator = "AND")
  {
    $this->query->where($fieldNameOrFieldValue, $value, $glue, $operator);
    return $this;
  }
  function filterNullWhere($fieldNameOrFieldValue, $value = null, $glue = "=", $operator = "AND")
  {
    $this->query->filterNullWhere($fieldNameOrFieldValue, $value, $glue, $operator);
    return $this;
  }
  function sql($yes = true)
  {
    $this->returnSql = $yes;
    return $this;
  }
  function getAll()
  {
    $sql = $this->query->get()->sql();
    if ($this->returnSql) return $sql;
    return $this->fetchAll($sql);
  }
  function getOne()
  {
    $sql = $this->query->limit(1)->get()->sql();
    if ($this->returnSql) return $sql;
    return $this->fetchOne($sql);
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql) return $sql;
    $countResult = $this->fetch($sql);
    if (!empty($countResult)) {
      return (int)$countResult["COUNT('$field')"];
    }
    return null;
  }
  function reset($flag = true)
  {
    $this->query->reset($flag);
    return $this;
  }

  public function fetchAll($sql, $mode = SQLITE3_ASSOC)
  {
    return $this->SQLite->fetchAll($sql, $mode);
  }
  public function fetch($sql, $mode = SQLITE3_ASSOC)
  {
    return $this->SQLite->fetch($sql, $mode);
  }
  public function fetchOne($sql, $mode = SQLITE3_ASSOC)
  {
    return $this->SQLite->fetchOne($sql, $mode);
  }
  public function query($sql)
  {
    return $this->SQLite->query($sql);
  }
}
