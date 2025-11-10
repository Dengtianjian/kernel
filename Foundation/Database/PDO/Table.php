<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Config;
use kernel\Foundation\Object\AbilityBaseObject;
use mysqli_result;

class Table extends AbilityBaseObject
{
  /**
   * 数据表名称
   *
   * @var string
   */
  protected $tableName = "";
  /**
   * 数据表结构SQL
   *
   * @var string
   */
  protected $tableStructureSQL = "";
  /**
   * 查询实例
   *
   * @var Query
   */
  protected $query;
  /**
   * 查询是否不执行，而是返回SQL
   *
   * @var boolean
   */
  protected $returnSql = false;
  /**
   * DB静态类
   *
   * @var DB
   */
  protected $DB = null;
  protected $prefixReplaces = [
    "{AppId}" => F_APP_ID
  ];
  protected $querys = [
    "executeType" => "select",
    "where" => [],
    "order" => [],
    "fields" => []
  ];

  /**
   * 表类构建
   *
   * @param string $tableName 数据表名称
   */
  function __construct($tableName = null)
  {
    if ($tableName) {
      $this->tableName = $tableName;
    }
    $this->tableName = $this->prefix($this->tableName);

    $this->DB = DB::class;
  }
  private function _query()
  {
    $query = new Query($this->tableName);
  }
  /**
   * 表名添加前缀
   *
   * @param string $tableName 表名称
   * @return string 添加前缀后的表名称
   */
  public function prefix($tableName)
  {
    $prefix = $this->getPrefix();
    if ($prefix) {
      $tableName = "{$prefix}_{$tableName}";
    }
    return $tableName;
  }
  /**
   * 获取表名称
   * @return string
   */
  public function getTableName()
  {
    return $this->tableName;
  }
  /**
   * 获取表前缀
   * @return string
   */
  public function getPrefix()
  {
    if (Config::get("database/mysql/prefix")) {
      $prefix = Config::get("database/mysql/prefix");
      $prefix = str_replace(array_keys($this->prefixReplaces), array_values($this->prefixReplaces), $prefix);

      return is_array($prefix) ? join($prefix, "_") : $prefix;
    }

    return "";
  }
  /**
   * 创建表
   */
  function create()
  {
    if (empty($this->tableStructureSQL))
      return true;
    return DB::query($this->tableStructureSQL);
  }
  /**
   * 排序
   * @param string $field 字段
   * @param string $by 排序方式，DESC=倒序，ASC=正序
   * @return static
   */
  function order($field, $by = "ASC")
  {
    $this->query->order($field, $by);
    return $this;
  }
  /**
   * 获取指定字段
   * @param array $fieldNames 获取的字段名称
   * @return static
   */
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
  function whereNotNull($fields, $operator = "AND")
  {

  }
  function filterNullWhere($fieldNameOrFieldValue, $value = null, $glue = "=", $operator = "AND")
  {
    $this->query->filterNullWhere($fieldNameOrFieldValue, $value, $glue, $operator);
    return $this;
  }
  function getSQL($yes = true)
  {
    $this->returnSql = $yes;
    return $this;
  }
  function insert($data, $isReplaceInto = false)
  {
    $Call = get_class($this);
    if ($Call::$Timestamps) {
      $now = time();
      if ($Call::$CreatedAt) {
        $data[$Call::$CreatedAt] = $now;
      }
      if ($Call::$UpdatedAt) {
        $data[$Call::$UpdatedAt] = $now;
      }

      if ($Call::$TimestampFields && count($Call::$TimestampFields)) {
        foreach ($Call::$TimestampFields as $item) {
          $data[$item] = $now;
        }
      }
    }
    $sql = $this->query->insert($data, $isReplaceInto)->sql();
    if ($this->returnSql)
      return $sql;
    $DB = $this->DB;
    $InsertResult = $DB::query($sql);
    $InsertId = $DB::insertId();

    return $InsertId ?: $InsertResult;
  }
  function insertId()
  {
    $DB = $this->DB;
    return $DB::insertId();
  }
  function batchInsert($fieldNames, $values, $isReplaceInto = false)
  {
    $Call = get_class($this);
    if ($Call::$Timestamps) {
      $now = time();
      if ($Call::$CreatedAt) {
        array_push($fieldNames, $Call::$CreatedAt);
        foreach ($values as &$ValueItem) {
          array_push($ValueItem, $now);
        }
      }
      if ($Call::$UpdatedAt) {
        array_push($fieldNames, $Call::$UpdatedAt);
        foreach ($values as &$ValueItem) {
          array_push($ValueItem, $now);
        }
      }

      if ($Call::$TimestampFields && count($Call::$TimestampFields)) {
        foreach ($Call::$TimestampFields as $item) {
          $fieldNames[] = $item;
          foreach ($values as &$ValueItem) {
            array_push($ValueItem, $now);
          }
        }
      }
    }

    $sql = $this->query->batchInsert($fieldNames, $values, $isReplaceInto)->sql();
    if ($this->returnSql)
      return $sql;
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function update($data)
  {
    if (!$data)
      return 0;
    $Call = get_class($this);
    if ($Call::$Timestamps) {
      $now = time();
      if ($Call::$UpdatedAt) {
        $data[$Call::$UpdatedAt] = $now;
      }

      if ($Call::$TimestampFields && count($Call::$TimestampFields)) {
        foreach ($Call::$TimestampFields as $item) {
          $data[$item] = $now;
        }
      }
    }
    $sql = $this->query->update($data)->sql();
    if ($this->returnSql)
      return $sql;
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function batchUpdate($fieldNames, $values)
  {
    $Call = get_class($this);
    if ($Call::$Timestamps) {
      $now = time();
      if ($Call::$UpdatedAt) {
        $data[$Call::$UpdatedAt] = $now;
      }

      if ($Call::$TimestampFields && count($Call::$TimestampFields)) {
        foreach ($Call::$TimestampFields as $item) {
          $data[$item] = $now;
        }
      }
    }
    $sql = $this->query->batchUpdate($fieldNames, $values)->sql();
    if ($this->returnSql)
      return $sql;
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function delete($directly = true)
  {
    if ($directly) {
      $sql = $this->query->delete($directly)->sql();
    } else {
      $data = [];
      $Call = get_class($this);
      if ($Call::$Timestamps) {
        $now = time();
        if ($Call::$UpdatedAt) {
          $data[$Call::$UpdatedAt] = $now;
        }
        if ($Call::$DeletedAt) {
          $data[$Call::$DeletedAt] = $now;
        }

        if ($Call::$TimestampFields && count($Call::$TimestampFields)) {
          foreach ($Call::$TimestampFields as $item) {
            $data[$item] = $now;
          }
        }
      }
      $sql = $this->query->update($data)->sql();
    }

    if ($this->returnSql)
      return $sql;
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function getAll()
  {
    if ($this->returnSql)
      return $this->query->get()->sql();
    $DB = $this->DB;
    return $DB::getAll($this->query);
  }
  function getOne()
  {
    if ($this->returnSql)
      return $this->query->limit(1)->get()->sql();
    $DB = $this->DB;
    return $DB::getOne($this->query);
  }
  function each($callback)
  {
    if ($this->returnSql)
      return $this->query->get()->sql();
    $DB = $this->DB;
    $DB::each($this->query, $callback);
    return $this;
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql)
      return $sql;
    $DB = $this->DB;
    $countResult = $DB::query($sql);
    if (!empty($countResult)) {
      return (int) $countResult['0']["COUNT('$field')"];
    }
    return null;
  }
  function exist()
  {
    $sql = $this->query->exist()->sql();
    if ($this->returnSql)
      return $sql;
    $DB = $this->DB;
    $exist = $DB::query($sql);
    if ($exist instanceof mysqli_result) {
      $exist = $exist->fetch_assoc();
      if (!empty($exist)) {
        $exist = $exist[array_keys($exist)[0]];
      }
    } else if (is_array($exist)) {
      $exist = $exist[array_keys($exist)[0]];
    }
    return boolval($exist);
  }
  function reset($flag = true)
  {
    $this->query->reset($flag);
    return $this;
  }
  /**
   * 执行sql
   *
   * @param string $sql sql语句
   * @return mixed
   */
  function query($sql)
  {
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function increment($field, $value = 1)
  {
    $sql = $this->query->increment($field, $value)->sql();
    if ($this->returnSql)
      return $sql;
    return (int) DB::query($sql);
  }
  function decrement($field, $value = 1)
  {
    $sql = $this->query->decrement($field, $value)->sql();
    if ($this->returnSql)
      return $sql;
    return (int) DB::query($sql);
  }
}
