<?php

namespace kernel\Modules\QCloud;

use kernel\Foundation\Module\Module;

/**
 * 腾讯云模块
 *
 * 腾讯云账号注册表：只回答「这次调用该用哪个账号」，不负责凭据本身
 * （见 {@see QCloudAccount}），也不负责 HTTP 请求与 TC3 签名（见 {@see QCloudApi}）。
 * 客户端取用入口全部挂在账号对象上（见 {@see QCloudAccount::api()}、
 * {@see QCloudAccount::faceId()}、{@see QCloudAccount::cos()}），本模块只做注册表。
 *
 * 与 {@see \kernel\Modules\WechatPay\WechatPayModule} 的分工一致，差别只在账号来源：
 * 微信支付模块无商户表、注册表只存在于当前请求内存中，本模块同样是**纯内存注册表**
 * （暂无账号表、不触发任何懒加载），由调用方通过 {@see addAccount()} 显式登记，仅当前请求有效。
 * 将来账号要落表时，只需在 {@see account()} 未命中处补一次懒加载，对外接口不变。
 *
 * 注册表以**账号标识（name）**为键：腾讯云没有像微信 appId、支付商户号那样的业务自然键，
 * 所以标识由调用方自定义（如 `main` / `cos`），见 {@see QCloudAccount::name()}。
 * 正因为键本身就是自定义字符串，本模块**不提供别名**（别名只会是同一标识的第二套写法）。
 *
 * 取用账号时必须显式给出标识，本模块不提供「默认账号」概念：
 * 无法从登记数量、登记顺序推断业务想用哪一个，猜错会静默拿错密钥发请求。
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
 */
class QCloudModule extends Module
{
  /**
   * 模块名称（App 模块管理器中的键名）
   *
   * @var string
   */
  protected $name = "qCloud";
  /**
   * 已登记的账号（账号标识 => QCloudAccount）
   *
   * @var array<string, QCloudAccount>
   */
  protected $accounts = [];

  // ===================================================================
  // 注册表
  // ===================================================================

  /**
   * 登记账号
   *
   * 账号标识取自 {@see QCloudAccount::name()}（构造时给出，或事后用 `name()` 设置）。
   * 同一标识重复登记时后者覆盖前者；本方法只影响当前请求的内存注册表，不写入任何表。
   *
   * @param QCloudAccount $account 账号实例，必须已带有非空标识
   * @return static 当前实例
   */
  public function addAccount(QCloudAccount $account)
  {
    $accountName = $account->name();
    if ($accountName === null || $accountName === "") {
      throw new \InvalidArgumentException("腾讯云账号缺少标识(name)，无法登记");
    }
    $this->accounts[$accountName] = $account;

    return $this;
  }
  /**
   * 移除账号
   *
   * 仅作用于当前请求的内存注册表，不修改任何表。
   *
   * @param string $accountName 账号标识
   * @return static 当前实例
   */
  public function removeAccount($accountName)
  {
    unset($this->accounts[$accountName]);

    return $this;
  }
  /**
   * 账号是否已登记
   *
   * @param string $accountName 账号标识
   * @return bool
   */
  public function hasAccount($accountName): bool
  {
    if ($accountName === null || $accountName === "") {
      return false;
    }

    return $this->account($accountName) !== null;
  }
  /**
   * 获取全部已登记账号
   *
   * @return array<string, QCloudAccount> 账号标识 => 账号
   */
  public function getAccounts(): array
  {
    return $this->accounts;
  }

  // ===================================================================
  // 取用入口
  // ===================================================================

  /**
   * 获取账号
   *
   * @param string $accountName 账号标识（必填，本模块不提供默认账号）
   * @return QCloudAccount|null 账号未登记时返回 null
   */
  public function account($accountName)
  {
    return $this->accounts[$accountName] ?? null;
  }
}
