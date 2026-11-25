<?php

namespace kernel\Discuz\WechatPay;

use kernel\Modules\WechatPay\WechatPayMerchant;

/**
 * Discuz! X 环境的微信支付商户
 *
 * 与 {@see WechatPayMerchant} 的唯一差别是**执行者不同**：本类额外持有一个
 * {@see DiscuzWechatPayChannel}，由它把下单请求交给 Discuz! 自带的 `pay_wechat`；
 * 其余凭证、验签密钥、`hasApiV3Credentials()` 判定全部沿用父类。
 *
 * 凭证**自动取自 Discuz! 后台的微信支付设置**（`ec_wechat`），与 `pay_wechat` 用的是同一份数据，
 * 因此构造时不必再逐个设置：`appid`、`mch_id`、`v3_key`、`v3_private_key`、`v3_serial_no`、
 * `v3_certificates` 都会自动灌入。构造参数可省略（不传就取 `ec_wechat` 里的值），
 * 传了则以传入值为准；构造之后仍可用同名 setter 覆盖任意一项。
 *
 * 有两项 `ec_wechat` 里没有，必须由调用方补：
 * - **微信支付公钥**：公钥模式下验签用，`platformPublicKeyContent()` 或 `platformPublicKeyFilePath()`；
 * - **回调地址**：Discuz! 写死指向自己的脚本，必须改成自己的入口 `notifyUrl()`。
 *
 * 使用示例：
 *
 * ```php
 * $merchant = new DiscuzWechatPayMerchant();      // 凭证全部来自 ec_wechat
 * $merchant
 *   ->platformPublicKeyContent($wechatPayPublicKeyPem)
 *   ->notifyUrl("https://example.com/source/plugin/xxx/wechatpay_notify.php");
 *
 * $merchant->v3();        // DiscuzWechatPayV3：先走 Discuz!，失败回退直连
 * $merchant->channel();   // DiscuzWechatPayChannel：实际执行 Discuz! 支付的对象
 * ```
 *
 * 构造期读取 `ec_wechat` 需要 Discuz! 已初始化（能查 `common_setting`），装配阶段是满足的；
 * 也正因如此，本类只能在 Discuz! 运行时实例化，CLI 下无法构造。
 */
class DiscuzWechatPayMerchant extends WechatPayMerchant
{
  /**
   * 执行 Discuz! 支付的通道（惰性构造，整个商户实例共用一份）
   *
   * 与商户同寿命：`pay_wechat` 构造时会读一次 `ec_wechat` 设置，
   * 因此后台改配置后需重新实例化商户才会生效。
   *
   * @var DiscuzWechatPayChannel|null
   */
  protected $channel = null;
  /**
   * 构造期灌入 `ec_wechat` 凭证时遇到的问题
   *
   * 键为设置项名（如 `v3_private_key`），值为异常信息。这里**不抛出**：后台配了一把坏私钥
   * 不该让整个应用启动失败，问题会同时体现在 {@see hasApiV3Credentials()} 与签名报错上。
   *
   * @var array<string, string>
   */
  protected $discuzSettingsErrors = [];

  /**
   * 初始化商户：凭证自动取自 Discuz! 后台的微信支付设置（`ec_wechat`）
   *
   * 与 `pay_wechat` 用同一份配置，因此除「微信支付公钥」与「回调地址」（这两项 `ec_wechat`
   * 里没有）之外，其余都不必手工设置。构造参数只用于覆盖 `appid` / `mch_id`，
   * 不传就取后台的值；构造之后仍可用同名 setter 改任意一项。
   *
   * @param string|null $appId 公众平台 AppId；null 或空串时取 `ec_wechat.appid`
   * @param string|null $merchantId 商户号；null 或空串时取 `ec_wechat.mch_id`
   */
  public function __construct($appId = null, $merchantId = null)
  {
    $settings = $this->discuzSettings();

    parent::__construct(
      ($appId !== null && $appId !== "") ? $appId : ($settings["appid"] ?? ""),
      ($merchantId !== null && $merchantId !== "") ? $merchantId : ($settings["mch_id"] ?? "")
    );

    $this->applyDiscuzSettings($settings);
  }

  /**
   * 读取 `ec_wechat` 设置
   *
   * 通过通道读取而不是自己再查一次 `common_setting`：通道已持有同一份设置，
   * 重复查询既多余，也会在「通道被换成自定义实现」时与通道实际使用的配置不一致。
   *
   * @return array `ec_wechat` 设置；读不到时为空数组
   */
  protected function discuzSettings()
  {
    $settings = $this->channel()->settings;

    return is_array($settings) ? $settings : [];
  }

  /**
   * 把 `ec_wechat` 的凭证灌入商户
   *
   * 私钥与平台证书要过 setter 的格式校验，校验失败只记录、不中断构造。
   *
   * @param array $settings `ec_wechat` 设置
   * @return void
   */
  protected function applyDiscuzSettings(array $settings)
  {
    $this->apiV3Secret($settings["v3_key"] ?? "");
    $this->apiV3PrivateKeySerialNo($settings["v3_serial_no"] ?? "");

    try {
      //* 后台存的是 PEM 文本，直接走「内容」而不是落盘换路径
      $this->apiV3PrivateKeyContent($settings["v3_private_key"] ?? "");
    } catch (\Throwable $e) {
      $this->discuzSettingsErrors["v3_private_key"] = $e->getMessage();
    }

    try {
      //* 公钥模式下这张表为空数组，等价于「没有平台证书」
      $this->platformCertificates($settings["v3_certificates"] ?? []);
    } catch (\Throwable $e) {
      $this->discuzSettingsErrors["v3_certificates"] = $e->getMessage();
    }
  }

  /**
   * 构造期读取配置时遇到的问题
   *
   * @return array<string, string> 键为设置项名，值为错误信息；一切正常时为空数组
   */
  public function discuzSettingsErrors()
  {
    return $this->discuzSettingsErrors;
  }

  /**
   * 读写一体：获取或设置 Discuz! 支付通道
   *
   * 传参即设置并返回 `$this`（便于替换成自定义通道），不传参则取用当前通道；
   * 未设置时按当前回调地址惰性构造一个 {@see DiscuzWechatPayChannel}。
   *
   * @param DiscuzWechatPayChannel|null $channel 待设置的通道
   * @return static|DiscuzWechatPayChannel 设置时返回当前实例；读取时返回通道
   */
  public function channel($channel = null)
  {
    if (func_num_args()) {
      $this->channel = $channel;
      return $this;
    }

    if ($this->channel === null) {
      $this->channel = new DiscuzWechatPayChannel($this->notifyUrl());
    }

    return $this->channel;
  }

  /**
   * 读写一体：获取或设置支付结果通知地址
   *
   * 覆写父类是为了**同步到已构造的通道**：通道拥有独立的 `$notify_url`（来自
   * `pay_base` 的 public 属性），父类只记在自己的属性上，不同步就会出现
   * 「商户这边改了回调地址，下单仍用旧地址」。
   *
   * @param string|null $data 待设置的回调地址
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   * @throws \kernel\Foundation\Exception\Error 回调地址格式不合规时抛出
   */
  public function notifyUrl($data = null)
  {
    if (func_num_args()) {
      parent::notifyUrl($data);
      if ($this->channel !== null) {
        $this->channel->notify_url = $data;
      }
      return $this;
    }

    return parent::notifyUrl();
  }

  /**
   * 实例化 APIv3 接口客户端
   *
   * 返回 {@see DiscuzWechatPayV3}：产品方法优先交给 Discuz! 执行，
   * Discuz! 表达不了的能力（APP 支付、关单、合单、账单、分账、转账等）由父类直连。
   *
   * @return DiscuzWechatPayV3
   */
  public function v3()
  {
    return new DiscuzWechatPayV3($this);
  }
}
