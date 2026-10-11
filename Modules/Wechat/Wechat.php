<?php

namespace kernel\Modules\Wechat;

use kernel\Foundation\Facade;

/**
 * 微信模块门面（Facade）
 *
 * 静态入口，将调用转发到 App 模块管理器中名为 "wechat" 的 {@see WechatModule} 实例；
 * 模块未装载时自动装载并启动（load 会一并 boot，保证 onBoot 生效）。
 *
 * 注意：App 未实例化时 accessor() 返回 null，所有静态调用随之静默返回 null。
 *
 * 使用示例：
 *
 * ```php
 * $oa = Wechat::officialAccount("wx1234567890");        // 指定 appId
 * $mp = Wechat::miniProgram("wx1234567890");            // 指定 appId
 * Wechat::setAlias("wx1234567890", "mini-default");     // 登记别名（仅当前请求）
 * $mp2 = Wechat::miniProgram("mini-default");           // 按别名
 * $token = Wechat::account("default")->accessToken();   // token 仍归 WechatAccessToken
 * ```
 *
 * @method static string name() 获取模块名称
 * @method static bool booted() 模块是否已启动
 * @method static static addAccount(\kernel\Modules\Wechat\WechatAccount $account, string|null $alias = null) 登记账号（内存注册表，优先级最高）
 * @method static static setAlias(string $appId, string $alias) 登记账号别名（运行时概念，仅当前请求有效，不落库）
 * @method static static removeAccount(string $appIdOrAlias) 移除账号（同时清理其别名）
 * @method static bool hasAccount(string $appIdOrAlias) 账号是否存在
 * @method static array getAccounts() 获取全部已登记账号（appId => WechatAccount，会触发懒加载）
 * @method static \kernel\Modules\Wechat\WechatAccount|null account(string $appIdOrAlias) 获取账号（不限定类型）
 * @method static \kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccount|null officialAccount(string $appIdOrAlias) 获取公众号账号
 * @method static \kernel\Modules\Wechat\Miniprogram\WechatMiniProgram|null miniProgram(string $appIdOrAlias) 获取小程序账号
 * @method static void boot() 启动模块（仅执行一次）
 * @method static void shutdown() 停止模块（仅执行一次）
 */
class Wechat extends Facade
{
  /**
   * 解析微信模块实例
   *
   * 优先取 App 模块管理器中已装载的 "wechat" 模块；未装载时自动装载（并启动）。
   *
   * @return WechatModule|null App 未实例化时返回 null
   */
  protected static function accessor()
  {
    $app = getApp();
    if ($app === null) {
      return null;
    }
    if (!$app->modules()->has("wechat")) {
      // 用 load 而非 register：register 只登记不启动，onBoot 将永远不会执行
      $app->modules()->load(new WechatModule());
    }

    return $app->modules()->get("wechat");
  }
}
