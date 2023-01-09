<?php

namespace kernel\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Database\Model;

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
      Response::error(500, 500001, Lang::value("service_tablename_empty"));
    }
    self::$tableName = $callClass::$tableName;
    if (self::$ModelInstance === null) {
      self::$ModelInstance = new Model(self::$tableName);
    }

    return self::$ModelInstance;
  }
}
