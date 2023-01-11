<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Data\Str;
use kernel\Foundation\Date;

class Model
{
  public $tableName = "";
  protected Query $query;
  protected $returnSql = false;
  protected $DB = null;

  public static $Timestamps = true;
  public static $CreatedAt = "createdAt";
  public static $UpdatedAt = "updatedAt";
  public static $DeletedAt = "deletedAt";
  public static $TimestampFields = [];

  function __construct(string $tableName = null)
  {
    if ($tableName) {
      $this->tableName = $tableName;
    }
    $this->query = new Query($this->tableName);

    $this->DB = $GLOBALS['App']->DBStaticClass;
  }
  /**
   * 快速实例化
   *
   * @param string $tableName 表名称
   * @return Model
   */
  static function quick($tableName = null)
  {
    $class = get_called_class();
    return new $class($tableName);
  }
  function order(string $field, string $by = "ASC")
  {
    $this->query->order($field, $by);
    return $this;
  }
  function field(...$fieldNames)
  {
    $this->query->field($fieldNames);
    return $this;
  }
  function limit(int $startOrNumber, int $number = null)
  {
    $this->query->limit($startOrNumber, $number);
    return $this;
  }
  function page(int $pages, int $pageLimit = 110)
  {
    $this->query->page($pages, $pageLimit);
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
  function sql($yes = true)
  {
    $this->returnSql = $yes;
    return $this;
  }
  function insert(array $data, bool $isReplaceInto = false)
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
    if ($this->returnSql) return $sql;
    return $this->DB::query($sql);
  }
  function insertId()
  {
    return $this->DB::insertId();
  }
  function batchInsert(array $fieldNames, array $values, bool $isReplaceInto = false)
  {
    $sql = $this->query->batchInsert($fieldNames, $values, $isReplaceInto)->sql();
    if ($this->returnSql) return $sql;
    return $this->DB::query($sql);
  }
  function update(array $data)
  {
    $sql = $this->query->update($data)->sql();
    if ($this->returnSql) return $sql;
    return $this->DB::query($sql);
  }
  function batchUpdate(array $fieldNames, array $values)
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
    if ($this->returnSql) return $sql;
    return $this->DB::query($sql);
  }
  function delete(bool $directly = false)
  {
    if ($directly) {
      $sql = $this->query->delete()->sql();
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

    if ($this->returnSql) return $sql;
    return $this->DB::query($sql);
  }
  function getAll()
  {
    if ($this->returnSql) return $this->query->get()->sql();
    return $this->DB::getAll($this->query);
  }
  function getOne()
  {
    if ($this->returnSql) return $this->query->limit(1)->get()->sql();
    return $this->DB::getOne($this->query);
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql) return $sql;
    $countResult = $this->DB::query($sql);
    if (!empty($countResult)) {
      return (int)$countResult['0']["COUNT('$field')"];
    }
    return null;
  }
  function genId($prefix = "", $suffix = "")
  {
    $nowTime = Date::milliseconds();
    return $nowTime . substr(md5($prefix . time() . Str::generateRandomString(8) . $suffix), 0, 24 - strlen($nowTime));
  }
  function exist()
  {
    $sql = $this->query->exist()->sql();
    if ($this->returnSql) return $sql;
    $exist = $this->DB::query($sql);
    if (empty($exist)) {
      return 0;
    }
    return intval($exist[0]["1"]);
  }
}
