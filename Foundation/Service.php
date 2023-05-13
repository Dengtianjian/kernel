<?php

namespace kernel\Foundation;

use kernel\Foundation\ReturnResult\ReturnResult;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Service extends BaseObject
{
  /**
   * ReturnResult实例
   *
   * @var ReturnResult
   */
  protected $return = null;
  public function __construct()
  {
    $this->return = new ReturnResult(true);
  }

  private static $useParams = [];
  final static function setUseParams($params)
  {
    self::$useParams = $params;
  }
  final static function getUseParams()
  {
    return self::$useParams;
  }
}
