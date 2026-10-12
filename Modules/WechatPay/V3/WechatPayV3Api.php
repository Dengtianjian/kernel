<?php

namespace kernel\Modules\WechatPay\V3;

use kernel\Foundation\Exception\Error;
use kernel\Modules\WechatPay\WechatPayApi;
use kernel\Modules\WechatPay\WechatPayMerchant;

/**
 * 微信支付 APIv3 请求基类
 *
 * V3 侧各业务接口类的公共基类，与微信模块中「某一侧的 API 基类」
 * （{@see \kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi}、
 * {@see \kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi}）定位一致：
 * 继承 {@see WechatPayApi} 的「怎么发请求」，在其之上补足 V3 专属的「怎么签、怎么发」——
 * 接口基础地址、JSON 报文约定、RSA(SHA256) 签名、Authorization 鉴权头注入，
 * multipart 上传（图片凭证），以及回调通知的签名验证与密文解密。
 *
 * 与 V2 的差异（表单/XML 报文 + MD5 签名）不落在这里，由
 * {@see \kernel\Modules\WechatPay\WechatPayV2} 自行承担，避免把版本差异混进公共基类。
 *
 * 商户身份与凭证统一由 {@see WechatPayMerchant} 承载（≡ 微信模块的 {@see \kernel\Modules\Wechat\WechatAccount}），
 * 本类只持有其引用、不重复保存 APIv3 密钥、私钥路径、证书序列号与回调地址。
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
 * $api = new WechatPayV3Api($merchant);
 * $api->merchant();  // 取回商户（凭证取用入口）
 * ```
 */
class WechatPayV3Api extends WechatPayApi
{
  /**
   * 接口基础地址
   *
   * 由本类设定 V3 前缀；个别接口需临时切换域名时，子类可保存该属性、改值、请求后再改回。
   *
   * @var string
   */
  protected $apiUrl = "https://api.mch.weixin.qq.com/v3";
  /**
   * 微信支付商户
   *
   * 提供 AppId、商户号与 APIv3 密钥、私钥路径、证书序列号、回调地址。
   *
   * @var WechatPayMerchant
   */
  protected $merchant = null;

  /**
   * 初始化 APIv3 请求基类
   *
   * 身份信息与凭证均取自 $merchant，本类只按 V3 约定配置复用的 CURL 客户端
   * （JSON 报文 + 固定请求头）。
   *
   * @param WechatPayMerchant $merchant 微信支付商户
   */
  function __construct(WechatPayMerchant $merchant)
  {
    $this->merchant = $merchant;

    parent::__construct($merchant->appId(), $merchant->merchantId());

    $this->CURL->headers([
      'Content-Type' => 'application/json; charset=UTF-8',
      'Accept' => 'application/json',
      'User-Agent' => '*/*',
    ])->json(true);
  }

  /**
   * 获取当前微信支付商户
   *
   * 凭证取用入口，与 {@see \kernel\Modules\Wechat\WechatApi::account()} 的分工一致。
   *
   * @return WechatPayMerchant 当前绑定的商户
   */
  public function merchant()
  {
    return $this->merchant;
  }

  /**
   * 数据密文最小长度
   */
  const AUTH_TAG_LENGTH_BYTE = 16;

  /**
   * 解密结果通知的加密数据
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_5.shtml
   *
   * @param string $associatedData 附加数据
   * @param string $nonceStr 随机串
   * @param string $ciphertext 数据密文,Base64编码后的开启/停用结果数据密文
   * @return array 
   * @return string $id 通知ID,通知的唯一ID
   * @return string $create_time 通知创建时间.通知创建的时间，遵循rfc3339标准格式，格式为yyyy-MM-DDTHH:mm:ss+TIMEZONE，yyyy-MM-DD表示年月日，T出现在字符串中，表示time元素的开头，HH:mm:ss.表示时分秒，TIMEZONE表示时区（+08:00表示东八区时间，领先UTC 8小时，即北京时间）。例如：2015-05-20T13:29:35+08:00表示北京时间2015年05月20日13点29分35秒。
   * @return string $event_type 通知类型,通知的类型，支付成功通知的类型为TRANSACTION.SUCCESS
   * @return string $resource_type 通知数据类型,通知的资源数据类型，支付成功通知为encrypt-resource
   * @return array $resource 通知资源数据,json格式，见示例
   * @return string $summary 回调摘要
   */
  public function decryptNotifyData($associatedData, $nonceStr, $ciphertext)
  {
    $ciphertext = \base64_decode($ciphertext);
    if (strlen($ciphertext) <= self::AUTH_TAG_LENGTH_BYTE) {
      return null;
    }

    // openssl (PHP >= 7.1 support AEAD)
    if (PHP_VERSION_ID >= 70100 && in_array('aes-256-gcm', \openssl_get_cipher_methods())) {
      $ctext = substr($ciphertext, 0, -self::AUTH_TAG_LENGTH_BYTE);
      $authTag = substr($ciphertext, -self::AUTH_TAG_LENGTH_BYTE);

      $data = \openssl_decrypt(
        $ctext,
        'aes-256-gcm',
        $this->merchant->apiV3Secret(),
        \OPENSSL_RAW_DATA,
        $nonceStr,
        $authTag,
        $associatedData
      );
      return $data ? json_decode($data, true) : $data;
    }
    return null;
  }

  /**
   * 生成请求签名
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/wechatpay/wechatpay4_0.shtml
   *
   * 按 V3 约定拼接「请求方法\nURL\n时间戳\n随机串\n请求报文\n」，用商户私钥做
   * SHA256withRSA 签名后 Base64 编码；结果作为 Authorization 头中的 signature。
   *
   * @param string $uri 请求的绝对路径（含 /v3 前缀，如 /v3/pay/transactions/jsapi）
   * @param string $httpRequestMethod 请求方法，如 POST
   * @param int|string $time 时间戳（秒）
   * @param string $nonceStr 随机串
   * @param string $bodyJson 请求报文的 JSON 字符串；GET 请求传空字符串
   * @return string Base64 编码后的签名
   */
  public function generateSign($uri, $httpRequestMethod = "POST", $time, $nonceStr, $bodyJson)
  {
    return $this->signMessage(
      $httpRequestMethod . "\n" .
        "$uri\n" .
        $time . "\n" .
        $nonceStr . "\n" .
        $bodyJson . "\n"
    );
  }

  /**
   * 用商户私钥对一段原文做 SHA256withRSA 签名
   *
   * 两类用途共用：请求签名（{@see generateSign()} 拼好待签串后调用）与
   * 前端调起参数签名（JSAPI / APP 的 paySign，待签串规则与请求签名不同，各自拼好再传进来）。
   *
   * @param string $message 待签名原文
   * @return string Base64 编码后的签名
   */
  protected function signMessage($message)
  {
    if (!file_exists($this->merchant->apiV3PrivateKeyFilePath())) {
      throw new Error("微信支付处理失败", 500, 500,  "密钥文件不存在");
    }
    $privateKeyFile = file_get_contents($this->merchant->apiV3PrivateKeyFilePath());
    if (!$privateKeyFile) {
      throw new Error("微信支付处理失败", 500, 500,  "无法获取到密钥文件内容");
    }
    $merchantPrivateKey = openssl_pkey_get_private($privateKeyFile);
    openssl_sign($message, $rawSign, $merchantPrivateKey, 'sha256WithRSAEncryption');

    return base64_encode($rawSign);
  }

  /**
   * 生成 Authorization 头的取值
   *
   * 注意返回的是取值本身，不含 `WECHATPAY2-SHA256-RSA2048 ` 认证类型前缀，
   * 拼接由 {@see addAuthorizationToHeader()} 完成。
   *
   * @param string $nonceStr 随机串
   * @param int|string $time 时间戳（秒）
   * @param string $sign 签名（{@see generateSign()} 的结果）
   * @return string
   */
  public function generateAuthorizationValue($nonceStr, $time, $sign)
  {
    return sprintf(
      'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
      $this->merchant->merchantId(),
      $nonceStr,
      $time,
      $this->merchant->apiV3PrivateKeySerialNo(),
      $sign
    );
  }

  /**
   * 将签名写入后续请求的 Authorization 头
   *
   * @param string $nonceStr 随机串
   * @param int|string $time 时间戳（秒）
   * @param string $sign 签名（{@see generateSign()} 的结果）
   * @return static 当前实例，便于链式调用
   */
  public function addAuthorizationToHeader($nonceStr, $time, $sign)
  {
    $this->CURL->headers([
      "Authorization" => "WECHATPAY2-SHA256-RSA2048 " . $this->generateAuthorizationValue($nonceStr, $time, $sign),
    ]);
    return $this;
  }

  /**
   * 按 APIv3 约定发起一次已签名的请求
   *
   * 把每个接口都要重复的一串动作收在一处：取时间戳与随机串 → JSON 编码报文 →
   * 按「请求方法\nURL\n时间戳\n随机串\n报文\n」生成签名 → 写入 Authorization 头 →
   * 发送请求 → 判定响应。
   *
   * $uri 传**含 /v3 前缀的完整路径**（如 /v3/pay/transactions/jsapi）：
   * 签名必须用完整路径，而实际请求地址由 {@see $apiUrl} 拼接、只要相对路径，
   * 本方法统一在此剥离，避免「签名写一份路径、发请求写另一份」写岔。
   *
   * GET 请求的签名串必须带上查询串（与 {@see \kernel\Foundation\HTTP\Curl::appendQuery()}
   * 同一套 http_build_query 拼法），否则与实发 URL 不符、必然验签失败。
   *
   * @param string $httpMethod 请求方法，GET / POST / PUT / DELETE
   * @param string $uri 含 /v3 前缀的完整接口路径
   * @param array $body 请求报文（GET 请求传空数组）
   * @param array $query 附加的 query 参数
   * @param boolean $parseJson 响应是否按 JSON 解析为数组；账单文件等非 JSON 响应传 false 以取原始字符串
   * @return array|string|boolean 成功返回响应数据；无响应体的接口（如关单、投诉回复返回 HTTP 204）为 null，
   *   由各业务方法按自己的语义处理（关单会把它转成 true）。失败返回 false，
   *   错误状态已记在本实例上（错误信息见 {@see getError()} / {@see getErrorMessage()}）。
   *   注意：判断失败请用 `=== false`（成功也可能是空数组、空串或 null）；错误状态在**发起请求的那个实例**上，
   *   取错误信息时要保留该实例。
   */
  protected function request($httpMethod, $uri, $body = [], $query = [], $parseJson = true)
  {
    $httpMethod = strtoupper($httpMethod);
    $now = time();
    $nonce = $this->generateNonceString();
    $bodyJson = empty($body) ? "" : json_encode($body, JSON_UNESCAPED_UNICODE);

    $signUri = $uri;
    if ($httpMethod === "GET" && $query) {
      $signUri .= (strpos($uri, "?") === false ? "?" : "&") . http_build_query($query);
    }
    $this->addAuthorizationToHeader($nonce, $now, $this->generateSign($signUri, $httpMethod, $now, $nonce, $bodyJson));

    $relativeUri = $this->relativeUri($uri);
    switch ($httpMethod) {
      case "GET":
        $response = $this->get($relativeUri, $query);
        break;
      case "PUT":
        // 少数接口微信要求 PUT（如消费者投诉更新投诉通知回调地址），报文与 POST 同构
        $response = $this->put($relativeUri, $body, $query);
        break;
      case "DELETE":
        $response = $this->delete($relativeUri, $body, $query);
        break;
      default:
        $response = $this->post($relativeUri, $body, $query);
    }

    return $this->handleResponse($response, $parseJson);
  }

  /**
   * 上传文件（multipart/form-data）
   *
   * 目前仅消费者投诉的图片凭证上传走这条路：报文是 multipart（普通字段 + 文件二进制），
   * 签名规则与 JSON 请求不同——参与签名的请求报文主体是 **meta 的 JSON 串**，
   * 既不是整个 multipart 报文体、也不是文件内容，故本方法把 meta 收成参数、
   * 由本方法统一编码一次供签名与表单字段共用，避免两处各编码一次导致验签失败。
   *
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter2_1_1.shtml
   *
   * @param string $uri 含 /v3 前缀的完整接口路径
   * @param array $meta 媒体文件元信息，随报文以 JSON 串提交并参与签名
   * @param string $filePath 待上传文件的绝对路径
   * @param string $fileFieldName 文件在表单中的字段名
   * @param array $fields 额外的普通表单字段
   * @return array|boolean 成功返回微信响应数据（数组），失败返回 false，约定同 {@see request()}
   */
  protected function requestUpload($uri, $meta, $filePath, $fileFieldName = "file", $fields = [])
  {
    $now = time();
    $nonce = $this->generateNonceString();
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
    $this->addAuthorizationToHeader($nonce, $now, $this->generateSign($uri, "POST", $now, $nonce, $metaJson));

    $response = $this->CURL
      ->url($this->apiUrl . "/" . $this->relativeUri($uri))
      ->data(array_merge($fields, ["meta" => $metaJson]))
      ->upload($fileFieldName, $filePath)
      ->file()
      ->post();
    // 复用的 CURL 实例不会自动清掉上传文件，且上传会把实例置为非 JSON 模式：用完即还原，
    // 否则后续 JSON 请求会被当成 multipart 发出去
    $this->CURL->clearUploadFiles()->json(true);

    return $this->handleResponse($response);
  }

  /**
   * 判定响应结果
   *
   * {@see request()} 与 {@see requestUpload()} 共用：curl 层错误 → 500；HTTP 状态码 > 299 →
   * 记微信返回的错误码与描述；成功则清掉上一次残留的错误状态。
   *
   * @param \kernel\Foundation\HTTP\Curl $response 已发起的请求实例
   * @param boolean $parseJson 响应是否按 JSON 解析为数组
   * @return array|string|boolean 约定同 {@see request()}
   */
  protected function handleResponse($response, $parseJson = true)
  {
    if ($response->errorNo()) {
      return $this->break(500, 500, "服务器错误", $response->error());
    }

    $data = $this->getJsonData();
    if ($response->statusCode() > 299) {
      return $this->break($response->statusCode(), $data['code'] ?? "", $data['message'] ?? "服务器错误", $data);
    }

    // 同一实例可能被复用于多次请求：成功时清掉上一次残留的错误状态
    $this->resetError();

    return $parseJson ? $data : $this->CURL->getData();
  }

  /**
   * 把含 /v3 前缀的完整路径转成相对 {@see $apiUrl} 的路径
   *
   * @param string $uri 完整接口路径，如 /v3/pay/transactions/jsapi
   * @return string 相对路径，如 pay/transactions/jsapi
   */
  protected function relativeUri($uri)
  {
    $prefix = parse_url($this->apiUrl, PHP_URL_PATH);
    if ($prefix && strpos($uri, $prefix . "/") === 0) {
      return substr($uri, strlen($prefix) + 1);
    }

    return ltrim($uri, "/");
  }
}
