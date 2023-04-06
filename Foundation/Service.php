<?php

namespace kernel\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Service extends BaseObject
{
  private static $useParams = [];
  protected final static function setUseParams($params)
  {
    self::$useParams = $params;
  }
  final protected static function getUseParams()
  {
    return self::$useParams;
  }
}
