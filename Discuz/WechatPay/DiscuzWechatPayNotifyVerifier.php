<?php

namespace kernel\Discuz\WechatPay;

use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Modules\WechatPay\V3\WechatPayV3Api;
use kernel\Modules\WechatPay\WechatPayMerchant;

/**
 * 微信支付回调校验（Discuz! X 环境）
 *
 * Discuz! 自带的 `pay_wechat::v3_wechat_sign_verify()` 只按**平台证书序列号**查
 * `ec_wechat.v3_certificates`，在公钥模式下那张表永远为空，回调必然失败。本类按官方规则
 * 以 `Wechatpay-Serial` 分发验证材料，两种模式都能用，切换灰度期也能同时兼容：
 *
 * - 值以 `PUB_KEY_ID_` 开头 → 用**微信支付公钥**（{@see WechatPayMerchant::platformPublicKey()}）；
 * - 其余视为**平台证书序列号** → 取 {@see WechatPayMerchant::platformCertificates()} 中对应的一张。
 *
 * 验签用的报文必须是**原始请求体**：先 `json_decode` 再 `json_encode` 会因为空白、键序、
 * 转义差异导致验签失败，因此本类自己读 `php://input`，不接受已解析的数组。
 *
 * 业务入口（`source/plugin/xxx/wechatpay_notify.php`）用法：
 *
 * ```php
 * $merchant = new DiscuzWechatPayMerchant($appId, $mchId);
 * $merchant->apiV3Secret($apiV3Secret)
 *          ->platformPublicKeyContent($publicKeyPem)
 *          ->platformCertificates($certificates);   // 灰度期可同时配置
 *
 * $verifier = new DiscuzWechatPayNotifyVerifier($merchant);
 * $notification = $verifier->verifyFromRequest();
 * if ($notification === false) {
 *   // 验签失败必须回 4xx/5xx，让微信重发；不要回 SUCCESS
 *   $verifier->getErrorMessage();
 *   exit('{"code":"FAIL","message":"fail"}');
 * }
 *
 * if ($notification["event_type"] === "TRANSACTION.SUCCESS") {
 *   $resource = $notification["resource"];   // 已解密，含 out_trade_no、transaction_id、success_time 等
 *   // 按 out_trade_no 标记自己的订单
 * }
 * echo '{"code":"SUCCESS","message":"成功"}';
 * ```
 *
 * 通知 ID（`$notification['id']`）在微信侧重试时会保持不变，业务侧应据此去重。
 */
class DiscuzWechatPayNotifyVerifier extends AbilityBaseObject
{
  /**
   * 微信支付公钥 ID 的前缀
   *
   * 回调头 `Wechatpay-Serial` 以此开头时表示用公钥验签，否则是平台证书序列号。
   */
  const PUBLIC_KEY_ID_PREFIX = "PUB_KEY_ID_";
  /**
   * 默认允许的时间戳偏差（秒）
   *
   * 官方建议校验 ±5 分钟以防重放；传 0 关闭校验（仅限联调）。
   */
  const DEFAULT_TOLERANCE = 300;

  /**
   * 商户（提供验签密钥与 APIv3 密钥）
   *
   * @var WechatPayMerchant|null
   */
  protected $merchant = null;
  /**
   * 解密用的 APIv3 客户端（惰性构造）
   *
   * @var WechatPayV3Api|null
   */
  protected $api = null;

  /**
   * 初始化回调校验器
   *
   * @param WechatPayMerchant $merchant 微信支付商户
   */
  public function __construct(WechatPayMerchant $merchant)
  {
    $this->merchant = $merchant;
  }

  /**
   * 从当前请求校验并解析回调
   *
   * 请求头由 PHP 正常映射为 `HTTP_WECHATPAY_*`，与 Discuz! 自带实现的取法一致。
   *
   * @param integer $tolerance 允许的时间戳偏差（秒），0 表示不校验
   * @return array|boolean 成功返回完整通知（`resource` 已替换为解密后的明文）；
   *   失败返回 false，原因见 {@see getErrorMessage()}
   */
  public function verifyFromRequest($tolerance = self::DEFAULT_TOLERANCE)
  {
    $serialNo = $_SERVER["HTTP_WECHATPAY_SERIAL"] ?? "";
    $timestamp = $_SERVER["HTTP_WECHATPAY_TIMESTAMP"] ?? "";
    $nonce = $_SERVER["HTTP_WECHATPAY_NONCE"] ?? "";
    $signature = $_SERVER["HTTP_WECHATPAY_SIGNATURE"] ?? "";

    if ($serialNo === "" || $timestamp === "" || $nonce === "" || $signature === "") {
      return $this->break(400, "400:NotifyHeaderMissing", "微信支付通知缺少验签请求头");
    }

    return $this->verify($serialNo, $timestamp, $nonce, $signature, file_get_contents("php://input") ?: "", $tolerance);
  }

  /**
   * 校验并解析回调（显式传入请求头与原始报文）
   *
   * 与 {@see verifyFromRequest()} 的区别只是报文来源，便于单测与非常规入口
   * （如被反向代理包了一层、`php://input` 已被读过）。
   *
   * @param string $serialNo 请求头 Wechatpay-Serial（平台证书序列号或公钥 ID）
   * @param string $timestamp 请求头 Wechatpay-Timestamp
   * @param string $nonce 请求头 Wechatpay-Nonce
   * @param string $signature 请求头 Wechatpay-Signature（Base64）
   * @param string $rawBody 原始请求体（未解析的 JSON 文本）
   * @param integer $tolerance 允许的时间戳偏差（秒），0 表示不校验
   * @return array|boolean 成功返回完整通知；失败返回 false，原因见 {@see getErrorMessage()}
   */
  public function verify($serialNo, $timestamp, $nonce, $signature, $rawBody, $tolerance = self::DEFAULT_TOLERANCE)
  {
    $serialNo = trim((string) $serialNo);
    if ($serialNo === "") {
      return $this->break(400, "400:NotifySerialMissing", "微信支付通知缺少 Wechatpay-Serial");
    }

    if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
      return $this->break(401, "401:NotifyExpired", "微信支付通知已过期，时间戳偏差超过 {$tolerance} 秒", ["timestamp" => $timestamp]);
    }

    $publicKey = $this->publicKeyFor($serialNo);
    if ($publicKey === null) {
      return $this->break(401, "401:NotifyKeyMissing", "未配置与 {$serialNo} 对应的验签密钥", ["serial" => $serialNo]);
    }

    //* 验签串：时间戳\n随机串\n报文\n（最后一行同样要换行）
    $message = $timestamp . "\n" . $nonce . "\n" . $rawBody . "\n";
    $verified = @openssl_verify($message, base64_decode((string) $signature), $publicKey, "sha256WithRSAEncryption");
    if ($verified !== 1) {
      return $this->break(401, "401:NotifySignatureInvalid", "微信支付通知验签失败", ["serial" => $serialNo]);
    }

    $body = json_decode($rawBody, true);
    if (!is_array($body) || !isset($body["resource"]) || !is_array($body["resource"])) {
      return $this->break(400, "400:NotifyBodyInvalid", "微信支付通知报文无法解析");
    }

    //* 验签通过后再解密：先验后解，避免对伪造报文做无谓的解密运算
    $decrypted = $this->api()->decryptNotifyData(
      $body["resource"]["associated_data"] ?? "",
      $body["resource"]["nonce"] ?? "",
      $body["resource"]["ciphertext"] ?? ""
    );
    if (!is_array($decrypted)) {
      return $this->break(400, "400:NotifyDecryptFailed", "微信支付通知报文解密失败");
    }

    $body["resource"] = $decrypted;
    $this->resetError();

    return $body;
  }

  /**
   * 按序列号取验签材料
   *
   * 公钥模式与平台证书模式的判定只看前缀，因此灰度期两种签名混着来也能各自命中。
   *
   * @param string $serialNo 请求头 Wechatpay-Serial
   * @return string|null 公钥或平台证书 PEM；未配置时返回 null
   */
  protected function publicKeyFor($serialNo)
  {
    if (str_starts_with($serialNo, self::PUBLIC_KEY_ID_PREFIX)) {
      return $this->merchant->platformPublicKey();
    }

    $certificates = $this->merchant->platformCertificates();

    return $certificates[$serialNo] ?? null;
  }

  /**
   * 解密用的 APIv3 客户端
   *
   * 只借用 {@see WechatPayV3Api::decryptNotifyData()}，不发起任何请求。
   *
   * @return WechatPayV3Api
   */
  protected function api()
  {
    if ($this->api === null) {
      $this->api = new WechatPayV3Api($this->merchant);
    }

    return $this->api;
  }
}
