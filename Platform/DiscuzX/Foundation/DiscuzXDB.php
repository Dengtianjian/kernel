<?php

namespace kernel\Platform\DiscuzX\Foundation;

class DiscuzXDB extends \DB
{
  public static function getOne($query)
  {
    $query->tableName = \DB::table($query->tableName);
    $data = self::fetch_first($query->limit(1)->get()->sql());
    if (empty($data)) return null;
    return $data;
  }
  static function getAll($query = null)
  {
    $query->tableName = \DB::table($query->tableName);
    $data = self::fetch_all($query->get()->sql());
    if (empty($data)) return [];
    return $data;
  }
  static function batchInsert($query)
  {
    $query->tableName = \DB::table($query->tableName);
    return self::query($query->sql());
  }
  static function batchUpdate($query)
  {
    $query->tableName = \DB::table($query->tableName);
    return self::query($query->sql());
  }
  static function count($query)
  {
    $query->tableName = \DB::table($query->tableName);
    return self::query($query->sql());
  }
  static function exist($query)
  {
    $query->tableName = \DB::table($query->tableName);
    $queryResult = self::query($query->exist()->sql());
    if (empty($queryResult)) {
      return 0;
    }
    return $query[0]["1"];
  }
  
  //* 事务相关
  static function begin()
  {
    self::query("BEGIN");
  }
  static function commit()
  {
    self::query("commit");
  }
  static function rollback()
  {
    self::query("ROLLBACK");
  }
}
