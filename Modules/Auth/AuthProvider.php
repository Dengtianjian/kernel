<?php

namespace kernel\Modules\Auth;

use kernel\Discuz\Foundation\DiscuzApp;
use kernel\Discuz\Middlewares\GlobalDiscuzAuthMiddleware;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Middlewares\GlobalAuthMiddleware;

/**
 * 认证装配
 *
 * 构造即装配，只做两件事：
 * - 把 {@see AuthModule} 登记成应用单例（凭证操作与登录态都挂在它身上）；
 * - 按运行环境挂认证中间件（Discuz / 通用）。
 * 过期 token 由 `Crons/ClearAuthTokensCron` 低频清理，不在每次请求里执行。
 *
 * 业务方法不在这里，见 {@see AuthModule}；业务侧取用走门面 {@see Auth}。
 *
 * 登记方式：`$app->make(AuthProvider::class)` 或 `$app->provider(new AuthProvider(), "auth")`，
 * **必须传实例**——容器对「传类名」是延迟构造，本类不被构造则认证不会被装配。
 */
class AuthProvider extends AbilityBaseObject
{
  /**
   * 构造即装配
   *
   * @return void
   */
  public function __construct()
  {
    $auth = new AuthModule();

    //* 类名 + 短名两条路都指向同一实例：
    //* `$app->make(AuthModule::class)` / 门面 `Auth::xxx()` / `$app->auth()`
    $this->app()->provider($auth, AuthModule::class);
    $this->app()->provider($auth, "auth");

    $this->app()->middleware()->set(
      DiscuzApp::exists() ? GlobalDiscuzAuthMiddleware::class : GlobalAuthMiddleware::class
    );
  }
}
