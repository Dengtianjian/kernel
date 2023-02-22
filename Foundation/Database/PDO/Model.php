<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Config;
use kernel\Foundation\Data\Str;
use kernel\Foundation\Date;

class Model
{
  public $tableName = "";
  public $tableStructureSQL = "";
  protected $query;
  protected $returnSql = false;
  protected $DB = null;
  protected $prefixReplaces = [
    "{AppId}" => F_APP_ID
  ];

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
    $this->tableName = $this->prefix($this->tableName);

    $this->query = new Query($this->tableName);

    $this->DB = DB::class;
  }
  function __clone()
  {
    $this->query = clone $this->query;
  }
  /**
   * 调用静态方法
   *
   * @param string $name 方法名称
   * @param array $args 参数
   * @return Model
   */
  // public static function __callStatic($name, $args)
  // {
  //   $ClasName = get_called_class();
  //   $I = new $ClasName();
  //   // debug($I);
  //   return $I->$name(...$args);
  //   // call_user_func_array([$I, $name], $args);
  // }
  /**
   * 表名添加前缀
   *
   * @param string $tableName 表名称
   * @return string 添加前缀后的表名称
   */
  public function prefix($tableName)
  {
    if (Config::get("database/mysql/prefix")) {
      $prefix = Config::get("database/mysql/prefix");
      $prefix = str_replace(array_keys($this->prefixReplaces), array_values($this->prefixReplaces), $prefix);

      $tableName = $prefix . "_" . $tableName;
    }
    return $tableName;
  }
  /**
   * 快速实例化
   *
   * @param string $tableName 表名称
   */
  static function quick($tableName = null)
  {
    $class = get_called_class();
    if ($tableName) {
      return new $class($tableName);
    } else {
      return new $class();
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
  function limit($startOrNumber, $number = null)
  {
    $this->query->limit($startOrNumber, $number);
    return $this;
  }
  function page($pages, $pageLimit = 10)
  {
    $this->query->page($pages, $pageLimit);
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
  function sql($yes = true)
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
    if ($this->returnSql) return $sql;
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function insertId()
  {
    $DB = $this->DB;
    return $DB::insertId();
  }
  function batchInsert($fieldNames, $values, $isReplaceInto = false)
  {
    $sql = $this->query->batchInsert($fieldNames, $values, $isReplaceInto)->sql();
    if ($this->returnSql) return $sql;
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function update($data)
  {
    $sql = $this->query->update($data)->sql();
    if ($this->returnSql) return $sql;
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
    if ($this->returnSql) return $sql;
    $DB = $this->DB;
    return $DB::query($sql);
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
    $DB = $this->DB;
    return $DB::query($sql);
  }
  function getAll()
  {
    if ($this->returnSql) return $this->query->get()->sql();
    $DB = $this->DB;
    return $DB::getAll($this->query);
  }
  function getOne()
  {
    if ($this->returnSql) return $this->query->limit(1)->get()->sql();
    $DB = $this->DB;
    return $DB::getOne($this->query);
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql) return $sql;
    $DB = $this->DB;
    $countResult = $DB::query($sql);
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
    $DB = $this->DB;
    $exist = $DB::query($sql);
    if (empty($exist)) {
      return 0;
    }
    return intval($exist[0]["1"]);
  }
}
