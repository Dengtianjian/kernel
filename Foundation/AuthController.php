<?php

namespace kernel\Foundation;

class AuthController extends Controller
{
  //* verifyAdmin 和 verifyAuth方法无需返回任何值，有问题直接响应调用Response即可
  /**
   * 校验管理员，默认是 false，也就是不校验
   */
  static $Admin = false; //* 可以是 布尔值 也可以是 函数，当 值 是true|1 或者 函数 返回是 true|1 时会执行 verifyAdmin 方法
  static function verifyAdmin(): void
  {
    // $calledAdmin = get_called_class()::$Admin;
  }
  /**
   * 校验权限。如果校验Admin没通过，就会校验Auth。当是 true 时，会校验请求是否携带了token，并且执行 verifyAuth 方法
   */
  static $Auth = false;  //* 可以是 布尔值 也可以是 函数。当 值是 true|1 或者 函数返回是 true|1 时会执行 verifyAuth 方法
  static function verifyAuth(): void
  {
    // Output::debug(self::$Admin);
  }
}
