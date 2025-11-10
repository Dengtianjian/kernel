<?php

namespace kernel\Foundation\Database\PDO;

use Kernel\Foundation\Database\Database;

class DB
{
  static function error()
  {
    return Database::getUseDriver()->error();
  }
  static function errno()
  {
    return Database::getUseDriver()->errno();
  }
  static function query($sql)
  {
    return Database::getUseDriver()->query($sql);
  }
  static function insertId()
  {
    return Database::getUseDriver()->insertId();
  }
  static function getOne($query)
  {
    $data = Database::getUseDriver()->fetch($query->limit(1)->get()->sql());
    if (empty($data))
      return null;

    return $data;
  }
  static function getAll($query = null)
  {
    $data = Database::getUseDriver()->fetchAll($query->get()->sql());
    if (empty($data))
      return [];

    return $data;
  }
  /**
   * @deprecated
   * @param mixed $query
   * @return bool|int|\PDOStatement
   */
  static function each($query)
  {
    $data = Database::getUseDriver()->query($query->get()->sql());
    return $data;
  }
  static function insert($query = null)
  {
    $InsertResult = Database::getUseDriver()->query($query->sql());
    if ($InsertResult && self::insertId()) {
      return self::insertId();
    }
    return $InsertResult;
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
  /**
   * 开始事务
   * @return bool 成功时返回 `true` ， 或者在失败时返回 `false`
   */
  static function begin()
  {
    return Database::getUseDriver()->beginTransaction();
  }
  /**
   * 提交一个事务
   * @return bool 成功时返回 `true` ， 或者在失败时返回 `false`
   */
  static function commit()
  {
    return Database::getUseDriver()->commit();
  }
  /**
   * 回滚事务
   * @return bool 成功时返回 `true` ， 或者在失败时返回 `false`
   */
  static function rollback()
  {
    return Database::getUseDriver()->rollBack();
  }
  /**
   * 检查是否在事务内
   * @return bool 成功时返回 `true` ， 或者在失败时返回 `false`
   */
  static function inTranscation()
  {
    return Database::getUseDriver()->inTranscation();
  }
}
