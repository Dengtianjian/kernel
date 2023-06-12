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

  /**
   * 使用服务
   *
   * @return void
   */
  public static function useService()
  {
  }
  /**
   * 初始化服务
   *
   * @return void
   */
  public static function init()
  {
  }
}
