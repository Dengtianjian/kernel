<?php

namespace kernel\Foundation;

use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Foundation\Result;



class Service extends AbilityBaseObject
{
  /**
   * Result实例
   *
   * @var Result
   */
  protected $return = null;
  public function __construct()
  {
    $this->return = new Result(true);
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
