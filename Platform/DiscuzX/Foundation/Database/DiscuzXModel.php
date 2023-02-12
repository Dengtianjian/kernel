<?php

namespace kernel\Platform\DiscuzX\Foundation\Database;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Data\Str;
use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Date;

class DiscuzXModel extends Model
{
  function __construct($tableName = null)
  {
    $this->tableName = \DB::table($tableName);

    $this->query = new DiscuzXQuery($tableName);

    $this->DB = \DB::class;
  }
  function createTable()
  {
    if (empty($this->tableStructureSQL)) return true;
    if (!function_exists("runquery")) {
      include_once libfile("function/plugin");
    }
    return runquery($this->tableStructureSQL);
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
    $this->query->tableName = \DB::table($this->query->tableName);
    if ($this->returnSql) return $sql;
    return \DB::query($sql);
  }
  function insertId()
  {
    return \DB::insert_id();
  }
  function batchInsert($fieldNames, $values, $isReplaceInto = false)
  {
    $sql = $this->query->batchInsert($fieldNames, $values, $isReplaceInto)->sql();
    if ($this->returnSql) return $sql;
    return DiscuzXDB::batchInsert($this->query);
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
    return \DB::query($sql);
  }
  function batchUpdate($fieldNames, $values)
  {
    $sql = $this->query->batchUpdate($fieldNames, $values)->sql();
    if ($this->returnSql) return $sql;
    return DiscuzXDB::batchUpdate($this->query);
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
    return \DB::query($this->query);
  }
  function getAll()
  {
    $sql = $this->query->get()->sql();
    if ($this->returnSql) return $sql;
    return DiscuzXDB::getAll($this->query);
  }
  function getOne()
  {
    $sql = $this->query->limit(1)->get()->sql();
    if ($this->returnSql) return $sql;
    $res = DiscuzXDB::getOne($this->query);
    if (empty($res)) return null;
    return $res;
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql) return $sql;
    return (int)DiscuzXDB::count($this->query);
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
    $exist = DiscuzXDB::exist($this->query);
    // if (empty($exist)) {
    //   return 0;
    // }
    return boolval($exist);
  }
}