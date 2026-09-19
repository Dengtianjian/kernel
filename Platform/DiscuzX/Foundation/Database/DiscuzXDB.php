<?php

namespace kernel\Platform\DiscuzX\Foundation\Database;

use mysqli_result;

class DiscuzXDB extends \DB
{
  public static function getOne($query)
  {
    $data = self::fetch_first($query->limit(1)->get()->sql());
    if (empty($data)) return null;
    return $data;
  }
  static function getAll($query = null)
  {
    $data = self::fetch_all($query->get()->sql());
    if (empty($data)) return [];
    return $data;
  }
  static function batchInsert($query)
  {
    return self::query($query->sql());
  }
  static function batchUpdate($query)
  {
    return self::query($query->sql());
  }
  static function count($query)
  {
    return self::result_first($query->sql());
  }
  static function exist($query)
  {
    $queryResult = self::count($query);
    return $queryResult;
  }
  static function insertId()
  {
    return self::insert_id();
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
