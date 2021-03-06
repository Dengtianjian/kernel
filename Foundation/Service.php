<?php

namespace kernel\Foundation;

use kernel\Foundation\Database\PDO\Model;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Service
{
  protected static $tableName = "";
  private static $ModelInstance = null;
  protected static function Model(): Model
  {
    $callClass = \get_called_class();
    if (!$callClass::$tableName) {
      Response::error(500, 500001, "缺失表名称");
    }
    self::$tableName = $callClass::$tableName;
    if (self::$ModelInstance === null) {
      self::$ModelInstance = new Model(self::$tableName);
    }

    return self::$ModelInstance;
  }
}
