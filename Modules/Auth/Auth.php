<?php

namespace kernel\Modules\Auth;

use kernel\Foundation\Facade;
use Override;

/**
 * Auth 门面
 *
 * 静态入口，将调用转发到已装载的 AuthModule 实例。
 * 底层实例由 App 模块管理器按名称 "auth" 解析；模块未装载时返回 null。
 *
 * @method static string name() 获取模块名称
 * @method static bool booted() 模块是否已启动
 * @method static \kernel\Modules\Auth\LoginsModel|static model(mixed $val = null) 获取或设置登录模型（传参时为设置并返回自身，不传时返回当前模型）
 * @method static array{value:string,salt:string,expiresAt:int,expireDays:int} generateToken(int $expireDays = 30) 生成 token（不落库），返回凭证数据（含 salt）
 * @method static array{value:string,salt:string,expiresAt:int,expireDays:int} createToken(string|int $userId, int $expireDays = 30) 生成并持久化登录 Token 到 logins 表（id 自增、salt/expire_days 一并落库），返回 generateToken 同款凭证数据
 * @method static void deleteToken(string $token) 按 token 值删除登录凭证（软删除）
 * @method static void deleteExpiredTokens() 清理过期登录凭证（物理删除）
 * @method static void deleteTokensByUser(string|int $userId) 吊销指定用户的全部登录凭证（软删除）
 * @method static static token(mixed $val) 设置当前请求解析出的 token，返回自身
 * @method static string|null token() 获取当前请求解析出的 token
 * @method static static tokenExpiresAt(mixed $val) 设置 token 过期时间（绝对时间戳），返回自身
 * @method static int|null tokenExpiresAt() 获取 token 过期时间（绝对时间戳）
 * @method static static logged(mixed $val) 设置登录状态，返回自身
 * @method static bool logged() 获取当前是否已登录
 * @method static static user(mixed $val) 设置当前登录用户数据，返回自身
 * @method static mixed user() 获取当前登录用户数据
 * @method static static userId(mixed $val) 设置当前登录用户 ID，返回自身
 * @method static string|int|null userId() 获取当前登录用户 ID
 * @method static void boot() 启动模块（仅执行一次）
 * @method static void shutdown() 停止模块（仅执行一次）
 */
class Auth extends Facade
{
  /**
   * 解析 Auth 模块实例
   *
   * @return AuthModule|null 模块未装载或 App 未实例化时返回 null
   */
  protected static function accessor(): ?AuthModule
  {
    $app = getApp();
    if ($app === null) {
      return null;
    }
    $module = null;
    if (!$app->modules()->has("auth")) {
      getApp()->modules()->register(new AuthModule());
    }
    $module = $app->modules()->get("auth");

    return $module;
  }
}
