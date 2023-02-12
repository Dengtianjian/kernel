<?php

namespace kernel\Foundation\Database\PDO;

class DB
{
  private static $db;
  static function driver($db)
  {
    self::$db = $db;
    return new static;
  }
  static function error()
  {
    return self::$db->error();
  }
  static function errno()
  {
    return self::$db->errno();
  }
  static function query(string $sql)
  {
    return self::$db->query($sql);
  }
  static function insertId()
  {
    return self::$db->insertId();
  }
  static function getOne($query)
  {
    $data = self::query($query->limit(1)->get()->sql());
    if (empty($data)) return null;
    return $data[0];
  }
  static function getAll($query = null)
  {
    $data = self::query($query->get()->sql());
    if (empty($data)) return [];
    return $data;
  }
  static function insert($query = null)
  {
    self::query($query->sql());
    return self::insertId();
  }
  static function batchInsert($query)
  {
    return self::query($query->sql());
  }
  static function update($query)
  {
    return self::query($query->sql());
  }
  static function batchUpdate($query)
  {
    return self::query($query->sql());
  }
  static function delete($query)
  {
    return self::query($query->sql());
  }
  static function count($query)
  {
    return self::query($query->sql());
  }
  static function exist($query)
  {
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
