<?php

namespace kernel\Foundation\Database\PDO;

class DB
{
  private static Driver $db;
  static function driver(Driver $db): DB
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
  static function getOne(Query $query)
  {
    $data = self::query($query->limit(1)->get()->sql());
    if (empty($data)) return null;
    return $data[0];
  }
  static function getAll(Query $query = null)
  {
    $data = self::query($query->get()->sql());
    if (empty($data)) return [];
    return $data;
  }
  static function insert(Query $query = null)
  {
    self::query($query->sql());
    return self::insertId();
  }
  static function batchInsert(Query $query)
  {
    return self::query($query->sql());
  }
  static function update(Query $query)
  {
    return self::query($query->sql());
  }
  static function batchUpdate(Query $query)
  {
    return self::query($query->sql());
  }
  static function delete(Query $query)
  {
    return self::query($query->sql());
  }
  static function count(Query $query)
  {
    return self::query($query->sql());
  }
  static function exist(Query $query)
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
