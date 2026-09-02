<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\Result;


/**
 * 带认证能力的控制器基类
 *
 * 在 {@see Controller} 基础上增加「认证开关 + 校验分发」能力：
 * - 公开属性 `$admin` / `$auth` 为认证开关，设为非 false 即启用对应认证；
 * - 启用后由框架的认证中间件（如 `GlobalAuthMiddleware`）读取这两个属性，执行 Token 校验，
 *   并在通过后将校验结果分发到本类的 `verifyAdmin()` / `verifyAuth()`；
 * - 子类可覆盖 `verifyAdmin()` / `verifyAuth()` 追加额外业务校验，返回 `Result` 表示校验结果。
 *
 * 默认实现的两个方法均返回成功态 `Result(null)`，即「仅做中间件级 Token 校验、不追加业务校验」。
 */
class AuthController extends Controller
{
  /**
   * 管理员认证开关
   *
   * 非 false 时启用管理员（Admin）认证，由中间件读取并触发 `verifyAdmin()` 分发。
   * @var bool|int|string|array
   */
  public $admin = false;

  /**
   * 普通用户认证开关
   *
   * 非 false 时启用用户（Auth）认证，由中间件读取并触发 `verifyAuth()` 分发。
   * @var bool|int|string|array
   */
  public $auth = false;

  /**
   * 管理员认证校验钩子
   *
   * 由认证中间件在 Token 校验通过后调用，子类可覆盖以追加管理员专属的业务校验。
   *
   * @return Result 校验结果（成功/失败）
   */
  function verifyAdmin(): Result
  {
    return new Result(null);
  }

  /**
   * 普通用户认证校验钩子
   *
   * 由认证中间件在 Token 校验通过后调用，子类可覆盖以追加用户专属的业务校验。
   *
   * @return Result 校验结果（成功/失败）
   */
  function verifyAuth(): Result
  {
    return new Result(null);
  }
}
