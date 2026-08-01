<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\ReturnResult\ReturnResult;


/**
 * 带认证能力的控制器基类
 *
 * $Admin / $Auth 属性启用认证，由 Middleware 执行 Token 校验及 verifyAdmin/verifyAuth 分发。
 * 子类可覆盖 verifyAdmin() / verifyAuth() 添加额外校验。
 */
class AuthController extends Controller
{
  /** @var bool|int|string|array */
  public $Admin = false;
  /** @var bool|int|string|array */
  public $Auth = false;

  function verifyAdmin(): ReturnResult
  {
    return new ReturnResult(null);
  }

  function verifyAuth(): ReturnResult
  {
    return new ReturnResult(null);
  }
}
