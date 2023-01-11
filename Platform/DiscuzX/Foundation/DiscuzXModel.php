<?php

namespace kernel\Platform\DiscuzX\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Data\Str;
use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\Query;
use kernel\Foundation\Date;

class DiscuzXModel extends Model
{
  function __construct($tableName = null)
  {
    parent::__construct($tableName);
    $this->tableName = $this->DB::table($this->tableName);
    $this->query = new Query($this->tableName);
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
    return $this->DB::query($sql);
  }
  function insertId()
  {
    return $this->DB::insert_id();
  }
  function batchInsert($fieldNames,  $values,  $isReplaceInto = false)
  {
    $sql = $this->query->batchInsert($fieldNames, $values, $isReplaceInto)->sql();
    if ($this->returnSql) return $sql;
    return $this->DB::query($sql);
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
    return $this->DB::query($sql);
  }
  function batchUpdate($fieldNames,  $values)
  {
    $sql = $this->query->batchUpdate($fieldNames, $values)->sql();
    if ($this->returnSql) return $sql;
    return $this->DB::query($sql);
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
    return $this->DB::query($sql);
  }
  function getAll()
  {
    $sql = $this->query->get()->sql();
    if ($this->returnSql) return $sql;
    return $this->DB::fetch_all($sql);
  }
  function getOne()
  {
    $sql = $this->query->limit(1)->get()->sql();
    if ($this->returnSql) return $sql;
    $res = $this->DB::fetch_all($sql);
    if (empty($res)) return null;
    return $res[0];
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql) return $sql;
    return (int)$this->DB::result($this->DB::query($sql));
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
    $exist = $this->DB::result($this->DB::query($sql));
    // if (empty($exist)) {
    //   return 0;
    // }
    return boolval($exist);
  }
}
