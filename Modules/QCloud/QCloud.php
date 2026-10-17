<?php

namespace kernel\Modules\QCloud;

use kernel\Foundation\Facade;

/**
 * 腾讯云模块门面（Facade）
 *
 * 静态入口，将调用转发到 App 模块管理器中名为 "qCloud" 的 {@see QCloudModule} 实例；
 * 模块未装载时自动装载并启动（load 会一并 boot，保证 onBoot 生效）。
 *
 * 注意：App 未实例化时 accessor() 返回 null，所有静态调用随之静默返回 null。
 *
 * 本类只是模块的静态入口，不承载任何请求逻辑：凭据与客户端取用入口（`api()` /
 * `faceId()` / `cos()`）见 {@see QCloudAccount}，请求客户端与 TC3 签名见 {@see QCloudApi}。
 *
 * 使用示例：
 *
 * ```php
 * QCloud::addAccount(new QCloudAccount($secretId, $secretKey, "main")); // 登记（应用侧）
 *
 * QCloud::account("main")->faceId()->bankCard2EVerification($name, $bankCard);
 * QCloud::account("main")->cos("ap-guangzhou", "bkt-1250000000")->upload("a/b.png", "/tmp/a.png");
 * QCloud::account("main")->api("faceid")->post("BankCard2EVerification", "2018-03-01", [...]);
 * ```
 *
 * @method static string name() 获取模块名称
 * @method static bool booted() 模块是否已启动
 * @method static static addAccount(\kernel\Modules\QCloud\QCloudAccount $account) 登记账号（内存注册表，仅当前请求有效）
 * @method static static removeAccount(string $accountName) 移除账号
 * @method static bool hasAccount(string $accountName) 账号是否已登记
 * @method static array getAccounts() 获取全部已登记账号（账号标识 => QCloudAccount）
 * @method static \kernel\Modules\QCloud\QCloudAccount|null account(string $accountName) 按标识获取账号（客户端入口挂在账号上：api() / faceId() / cos()）
 * @method static void boot() 启动模块（仅执行一次）
 * @method static void shutdown() 停止模块（仅执行一次）
 */
class QCloud extends Facade
{
  /**
   * 解析腾讯云模块实例
   *
   * 优先取 App 模块管理器中已装载的 "qCloud" 模块；未装载时自动装载（并启动）。
   *
   * @return QCloudModule|null App 未实例化时返回 null
   */
  protected static function accessor()
  {
    $app = getApp();
    if ($app === null) {
      return null;
    }
    if (!$app->modules()->has("qCloud")) {
      // 用 load 而非 register：register 只登记不启动，onBoot 将永远不会执行
      $app->modules()->load(new QCloudModule());
    }

    return $app->modules()->get("qCloud");
  }
}
