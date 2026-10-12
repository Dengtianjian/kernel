<?php

namespace kernel\Modules\WechatPay;

use kernel\Foundation\Facade;

/**
 * 微信支付模块门面（Facade）
 *
 * 静态入口，将调用转发到 App 模块管理器中名为 "wechatPay" 的 {@see WechatPayModule} 实例；
 * 模块未装载时自动装载并启动（load 会一并 boot，保证 onBoot 生效）。
 *
 * 注意：App 未实例化时 accessor() 返回 null，所有静态调用随之静默返回 null。
 *
 * 本类只是模块的静态入口，不承载任何请求逻辑：请求客户端基类见 {@see WechatPayApi}
 * （V3 见 {@see \kernel\Modules\WechatPay\V3\WechatPayV3Api}），商户凭证见 {@see WechatPayMerchant}。
 *
 * 使用示例：
 *
 * ```php
 * $merchant = new WechatPayMerchant($appId, $mchId);
 * $merchant
 *   ->apiV3Secret("...")
 *   ->apiV3PrivateKeyFilePath("/path/apiclient_key.pem")
 *   ->apiV3PrivateKeySerialNo("...")
 *   ->notifyUrl("https://example.com/wechatPayNotify.php");
 *
 * WechatPay::addMerchant($merchant, "main");   // 登记商户（仅当前请求）
 * WechatPay::merchant("main");                 // 按别名取用
 * WechatPay::hasMerchant("1900000109");        // 按商户号判存在
 *
 * $pay = new WechatPayV3(WechatPay::merchant("main"));
 * ```
 *
 * @method static string name() 获取模块名称
 * @method static bool booted() 模块是否已启动
 * @method static static addMerchant(\kernel\Modules\WechatPay\WechatPayMerchant $merchant, string|null $alias = null) 登记商户（内存注册表，优先级最高）
 * @method static static setAlias(string $merchantId, string $alias) 登记商户别名（运行时概念，仅当前请求有效，不落库）
 * @method static static removeMerchant(string $merchantIdOrAlias) 移除商户（同时清理其别名）
 * @method static bool hasMerchant(string $merchantIdOrAlias) 商户是否已登记
 * @method static array getMerchants() 获取全部已登记商户（merchantId => WechatPayMerchant）
 * @method static \kernel\Modules\WechatPay\WechatPayMerchant|null merchant(string $merchantIdOrAlias) 获取商户（按商户号或别名）
 * @method static void boot() 启动模块（仅执行一次）
 * @method static void shutdown() 停止模块（仅执行一次）
 */
class WechatPay extends Facade
{
  /**
   * 解析微信支付模块实例
   *
   * 优先取 App 模块管理器中已装载的 "wechatPay" 模块；未装载时自动装载（并启动）。
   *
   * @return WechatPayModule|null App 未实例化时返回 null
   */
  protected static function accessor()
  {
    $app = getApp();
    if ($app === null) {
      return null;
    }
    if (!$app->modules()->has("wechatPay")) {
      // 用 load 而非 register：register 只登记不启动，onBoot 将永远不会执行
      $app->modules()->load(new WechatPayModule());
    }

    return $app->modules()->get("wechatPay");
  }
}
