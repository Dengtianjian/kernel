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
  protected $dryRun = false;

  function __construct($tableName = null, $prefix = null)
  {
    if (!$tableName) {
      $tableName = $this->tableName;
    }
    if ($prefix) {
      $tableName = join("_", [
        $prefix,
        $tableName
      ]);
    }

    $this->tableName = $tableName;
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
  function insert($data, $isReplaceInto = false, $isIgnore = false)
  {
    $isBatch = isset($data[0]) && is_array($data[0]);

    $Call = get_class($this);
    if ($Call::$Timestamps) {
      $now = time();
      if ($isBatch) {
        foreach ($data as &$row) {
          if ($Call::$CreatedAt) {
            $row[$Call::$CreatedAt] = $now;
          }
          if ($Call::$UpdatedAt) {
            $row[$Call::$UpdatedAt] = $now;
          }
          if ($Call::$TimestampFields && count($Call::$TimestampFields)) {
            foreach ($Call::$TimestampFields as $item) {
              $row[$item] = $now;
            }
          }
        }
      } else {
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
    }
    $sql = $this->query->insert($data, $isReplaceInto, $isIgnore)->sql();
    if ($this->dryRun) return $sql;
    if ($isBatch) return \DB::query($sql);
    return \DB::query($sql);
  }
  function insertId()
  {
    return \DB::insert_id();
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
    if ($this->dryRun) return $sql;
    return \DB::query($sql);
  }
  function batchUpdate($fieldNames, $values)
  {
    $sql = $this->query->batchUpdate($fieldNames, $values)->sql();
    if ($this->dryRun) return $sql;
    return DiscuzXDB::batchUpdate($this->query);
  }
  function delete($directly = false)
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

    if ($this->dryRun) return $sql;
    return \DB::query($sql);
  }
  function getAll()
  {
    $sql = $this->query->get()->sql();
    if ($this->dryRun) return $sql;
    return DiscuzXDB::fetch_all($sql);
  }
  function getOne()
  {
    $sql = $this->query->limit(1)->get()->sql();
    if ($this->dryRun) return $sql;
    $res = DiscuzXDB::fetch_first($sql);
    if (empty($res)) return null;
    return $res;
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->dryRun) return $sql;
    return (int)\DB::result_first($sql);
  }
  function genId($prefix = "", $suffix = "")
  {
    $nowTime = Date::milliseconds();
    return $nowTime . substr(md5($prefix . time() . Str::generateRandomString(8) . $suffix), 0, 24 - strlen($nowTime));
  }
  function exist()
  {
    $sql = $this->query->count()->sql();
    if ($this->dryRun) return $sql;
    $exist = DiscuzXDB::result_first($sql);
    return boolval($exist);
  }
  function increment($field, $value = 1)
  {
    $sql = $this->query->increment($field, $value)->sql();
    if ($this->dryRun) return $sql;
    return (int)\DB::query($sql);
  }
  function decrement($field, $value = 1)
  {
    $sql = $this->query->decrement($field, $value)->sql();
    if ($this->dryRun) return $sql;
    return (int)\DB::query($sql);
  }
}
