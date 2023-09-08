<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\ReturnResult\ReturnResult;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class AuthController extends Controller
{
  public function __get($name)
  {
    return $this->$name;
  }

  /**
   * 校验管理员  
   * 布尔值：为true时，就说明需要校验管理员，会执行verifyAdmin方法  
   * ~~函数：当函数执行后返回为true时，就说明需要校验管理员，会执行verifyAdmin方法~~
   *
   * @var boolean|Closure
   */
  public $Admin = false;
  /**
   * 验证管理员  
   * 需要返回ReturnResult实例
   *
   * @return \kernel\Foundation\ReturnResult
   */
  function verifyAdmin()
  {
    // $calledAdmin = get_called_class()::$Admin;
    return new ReturnResult(false);
  }

  /**
   * 是否需要校验用户权限  
   * 布尔值：为true时会调用verifyAuth方法  
   * ~~函数：当返回true时会调用verifyAuth方法~~
   *
   * @var boolean|Closure
   */
  public $Auth = false;
  /**
   * 验证用户是否已登录
   *
   * @return \kernel\Foundation\ReturnResult
   */
  function verifyAuth()
  {
    // Output::debug(self::$Admin);
    return new ReturnResult(false);
  }
}
