<?php

namespace kernel\Providers\Wechat;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 微信服务装配
 *
 * 构造函数里一次做完两件事：
 * - 把 {@see Wechat} 绑成应用单例——装配阶段 `addAccount()` 登记的账号
 *   与业务里 `miniProgram()` 取到的，必须是同一个注册表；
 * - 登记本应用的账号。默认一个都没有（内核不知道应用用哪些 appId），
 *   应用侧继承本类覆盖 {@see accounts()} 返回账号表即可。
 *
 * 登记方式：`$app->register(new WechatProvider())`。**必须传实例**——容器对「传类名」
 * 的登记是延迟的（首次取用才构造），本类不被构造就不会登记账号。
 *
 * 默认不对外开放：公众号 / 小程序凭据属于装配它的应用；要用请先 `expose()`。
 *
 * @see \kernel\Providers\Wechat\Wechat
 */
class WechatProvider extends AbilityBaseObject
{
  /**
   * 构造即装配：绑服务 → 登记账号
   *
   * 空表时不取服务，避免无谓地构建注册表。
   *
   * @return void
   */
  public function __construct()
  {
    $this->app()->provider(Wechat::class);

    $accounts = $this->accounts();
    if (!$accounts) {
      return;
    }

    $wechat = $this->app()->make(Wechat::class);
    foreach ($accounts as $alias => $account) {
      //* 数字下标的写法没有别名，按 appId 取用
      $wechat->addAccount($account, is_string($alias) ? $alias : null);
    }
  }

  /**
   * 本应用的微信账号（别名 => 账号实例）
   *
   * 默认空表。**在构造函数里调用**，此刻 `setup()` 已完成、数据库已接，可以放心读设置；
   * 但要注意本方法在父类构造期间执行：子类若另有属性参与计算，必须先于
   * `parent::__construct()` 就绪。
   *
   * ```php
   * protected function accounts()
   * {
   *   $settings = $this->app()->make(SettingProvider::class)->items("Wechat_MiniProgramAppId", ...);
   *
   *   return ["main" => new WechatMiniProgram($settings["Wechat_MiniProgramAppId"], $settings["Wechat_MiniProgramAppSecret"])];
   * }
   * ```
   *
   * @return array<string|int, WechatAccount>
   */
  protected function accounts()
  {
    return [];
  }
}
