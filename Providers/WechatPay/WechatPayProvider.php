<?php

namespace kernel\Providers\WechatPay;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 微信支付服务装配
 *
 * 构造函数里一次做完两件事：
 * - 把 {@see WechatPay} 绑成应用单例：商户注册表必须整个请求共用一份，
 *   否则装配阶段登记的商户与业务里取到的商户会是两个空表；
 * - 登记本应用的商户。默认一个都没有（内核不知道应用的商户号与证书在哪），
 *   应用侧继承本类覆盖 {@see merchants()} 返回商户表即可。
 *
 * 商户依赖小程序的 appId（JSAPI 下单要带 appid），所以应用侧通常在登记完小程序账号
 * 之后再登记商户——注册这两个 Provider 时先 {@see WechatProvider} 再本类即可。
 *
 * 登记方式：`$app->register(new WechatPayProvider())`。**必须传实例**——容器对「传类名」
 * 的登记是延迟的（首次取用才构造），本类不被构造就不会登记商户。
 *
 * 默认不对外开放：商户号与证书路径属于装配它的应用；要用请先 `expose()`。
 *
 * @see \kernel\Providers\WechatPay\WechatPay
 */
class WechatPayProvider extends AbilityBaseObject
{
  /**
   * 构造即装配：绑服务 → 登记商户
   *
   * 空表时不取服务，避免无谓地构建注册表。
   *
   * @return void
   */
  public function __construct()
  {
    $this->app()->provider(WechatPay::class);

    $merchants = $this->merchants();
    if (!$merchants) {
      return;
    }

    $wechatPay = $this->app()->make(WechatPay::class);
    foreach ($merchants as $alias => $merchant) {
      //* 数字下标的写法没有别名，按商户号取用
      $wechatPay->addMerchant($merchant, is_string($alias) ? $alias : null);
    }
  }

  /**
   * 本应用的支付商户（别名 => 商户实例）
   *
   * 默认空表。**在构造函数里调用**，此刻 `setup()` 已完成、数据库已接，可以放心读设置；
   * 但要注意本方法在父类构造期间执行：子类若另有属性参与计算，必须先于
   * `parent::__construct()` 就绪。
   *
   * ```php
   * protected function merchants()
   * {
   *   $settings = $this->app()->make(SettingProvider::class)->items("WechatPay_MerchantId", ...);
   *   $merchant = new WechatPayMerchant($settings["Wechat_MiniProgramAppId"], $settings["WechatPay_MerchantId"]);
   *   $merchant->apiV3PrivateKeySerialNo(...);
   *
   *   return ["main" => $merchant];
   * }
   * ```
   *
   * @return array<string|int, WechatPayMerchant>
   */
  protected function merchants()
  {
    return [];
  }
}
