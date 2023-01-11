<?php

namespace gstudio_kernel\Foundation\Database;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use DB;
use gstudio_kernel\Foundation\Data\Str;
use gstudio_kernel\Foundation\Database\Query;
use gstudio_kernel\Foundation\Date;
use gstudio_kernel\Foundation\Output;

class Model
{
  public $tableName = "";
  private $query;
  private $returnSql = false;

  public static $Timestamps = true;
  public static $CreatedAt = "createdAt";
  public static $UpdatedAt = "updatedAt";
  public static $DeletedAt = "deletedAt";
  public static $TimestampFields = [];

  function __construct($tableName = null)
  {
    if ($tableName) {
      $this->tableName = $tableName;
    }
    $this->tableName = DB::table($this->tableName);
    $this->query = new Query($this->tableName);
  }
  /**
   * 快速实例化
   *
   * @param string $tableName 表名称
   * @return Model
   */
  static function ins($tableName = null)
  {
    $class = get_called_class();
    return new $class($tableName);
  }
  function order($field,  $by = "ASC")
  {
    $this->query->order($field, $by);
    return $this;
  }
  function field(...$fieldNames)
  {
    $this->query->field($fieldNames);
    return $this;
  }
  function limit($startOrNumber,  $number = null)
  {
    $this->query->limit($startOrNumber, $number);
    return $this;
  }
  function page($pages,  $pageLimit = 110)
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
  function insert($data,  $isReplaceInto = false)
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
    return DB::query($sql);
  }
  function insertId()
  {
    return DB::insert_id();
  }
  function batchInsert($fieldNames,  $values,  $isReplaceInto = false)
  {
    $sql = $this->query->batchInsert($fieldNames, $values, $isReplaceInto)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function update($data)
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
    $sql = $this->query->update($data)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function batchUpdate($fieldNames,  $values)
  {
    $sql = $this->query->batchUpdate($fieldNames, $values)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function delete($directly = false)
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
    return DB::query($sql);
  }
  function getAll()
  {
    $sql = $this->query->get()->sql();
    if ($this->returnSql) return $sql;
    return DB::fetch_all($sql);
  }
  function getOne()
  {
    $sql = $this->query->limit(1)->get()->sql();
    if ($this->returnSql) return $sql;
    $res = DB::fetch_all($sql);
    if (empty($res)) return null;
    return $res[0];
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql) return $sql;
    return (int)DB::result(DB::query($sql));
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
    $exist = DB::result(DB::query($sql));
    // if (empty($exist)) {
    //   return 0;
    // }
    return boolval($exist);
  }
}
