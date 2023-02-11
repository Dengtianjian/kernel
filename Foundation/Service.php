<?php

namespace kernel\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Exception\Exception;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Service
{
  protected static $tableName = "";
  private static $ModelInstance = null;
  protected static function Model()
  {
    $callClass = \get_called_class();
    if (!$callClass::$tableName) {
      throw new Exception("服务的表名称缺失",500,500001);
    }
    self::$tableName = $callClass::$tableName;
    if (self::$ModelInstance === null) {
      self::$ModelInstance = new Model(self::$tableName);
    }

    return self::$ModelInstance;
  }
}
