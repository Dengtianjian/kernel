<?php

namespace gstudio_kernel\Foundation\Controller;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Response;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class AuthController extends BaseController
{
  public function __get($name)
  {
    return $this->$name;
  }

  //* verifyAdmin 和 verifyAuth方法无需返回任何值，有问题直接响应调用Response即可
  static $Admin = false; //* 可以是 布尔值 也可以是 函数，当 值 是true|1 或者 函数 返回是 true|1 时会执行 verifyAdmin 方法，也可以是一个数组，里面存放可以访问该路由的用户组ID
  static $AdminMethods = null; //* 需要 校验管理员的请求方法，主要是针对resource类型的路由
  static function verifyAdmin()
  {
    // $calledAdmin = get_called_class()::$Admin;
  }

  /**
   //* 校验权限。如果校验Admin没通过，就会校验Auth。当是 true 时，会校验请求是否携带了token，并且执行 verifyAuth 方法
   */
  static $Auth = false; //* 可以是 布尔值 也可以是 函数。当 值是 true|1 或者 函数返回是 true|1 时会执行 verifyAuth 方法，也可以是一个数组，里面存放可以访问该路由的用户组ID
  static $AuthMethods = null; //* 需要 鉴权的请求方法，主要是针对resource类型的路由
  static function verifyAuth()
  {
    // Output::debug(self::$Admin);
  }

  static $Formhash = false;
  final static public function verifyFormhash()
  {
    global $App;
    if (self::$Formhash) {
      if (!$App->request->params("formhash") || $App->request->params("formhash") != \FORMHASH) {
        Response::error("LLLEGAL_SUBMISSION");
      }
      $App->request->remove("formhash");
    }
  }
}
