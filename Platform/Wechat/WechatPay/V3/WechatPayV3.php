<?php

namespace kernel\Platform\Wechat\WechatPay\V3;

use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Platform\Wechat\WechatPay\WechatPay;

class WechatPayV3 extends WechatPay
{
  /**
   * 接口基础地址
   *
   * @var string
   */
  protected $ApiUrl = "https://api.mch.weixin.qq.com/v3";
  /**
   * APIv3密钥
   *
   * @var string
   */
  protected $ApiV3Secret = null;
  /**
   * API证书文件路径
   *
   * @var string
   */
  protected $PrivateKeyFilePath = null;
  /**
   * API证书序列号
   *
   * @var string
   */
  protected $PrivateKeySerialNo = null;
  /**
   * 回调通知地址
   * 异步接收微信支付结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。 公网域名必须为https，如果是走专线接入，使用专线NAT IP或者私有回调域名可使用http
   *
   * @var string
   */
  protected $NotifyURL = null;
  /**
   * 实例化微信支付JSAPI
   * @param string $AppId 公众平台应用ID 由微信生成的应用ID，全局唯一。请求基础下单接口时请注意APPID的应用属性，例如公众号场景下，需使用应用属性为公众号的服务号APPID
   * @param string $MerchantId 直连商户号 由微信支付生成并下发。
   * @param string $ApiV3Secret APIV3密钥 https://pay.weixin.qq.com/wiki/doc/apiv3/wechatpay/wechatpay3_2.shtml
   * @param string $PrivateKeyFilePath 商户私钥文件路径 https://pay.weixin.qq.com/wiki/doc/apiv3/wechatpay/wechatpay3_1.shtml
   * @param string $PrivateKeySerialNo 商户密钥序列号 
   */
  function __construct($AppId, $MerchantId, $ApiV3Secret, $PrivateKeyFilePath, $PrivateKeySerialNo, $NotifyURL)
  {
    $this->ApiV3Secret = $ApiV3Secret;
    $this->PrivateKeyFilePath = $PrivateKeyFilePath;
    $this->PrivateKeySerialNo = $PrivateKeySerialNo;
    $this->NotifyURL = $NotifyURL;

    parent::__construct($AppId, $MerchantId);

    $this->CURL->headers([
      'Content-Type' => 'application/json; charset=UTF-8',
      'Accept' => 'application/json',
      'User-Agent' => '*/*',
    ])->json(true);
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
        $this->ApiV3Secret,
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
   * 分账
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_1.shtml
   *
   * @param string $transactionId 微信支付订单号
   * @param string $outOrderNo 商户系统内部的分账单号，在商户系统内部唯一，同一分账单号多次请求等同一次。只能是数字、大小写字母_-|*@
   * @param arra $receivers 账接收方列表，可以设置出资商户作为分账接受方，最多可有50个分账接收方
   * @param boolean $unfreezeUnsplit 1、如果为true，该笔订单剩余未分账的金额会解冻回分账方商户；2、如果为false，该笔订单剩余未分账的金额不会解冻回分账方商户，可以对该笔订单再次进行分账。
   * @return ReturnResult
   */
  public function profitsharing($transactionId, $outOrderNo, $receivers, $unfreezeUnsplit = false)
  {
    $HTTPMethod = "POST";
    $Body = [
      "appid" => $this->AppId,
      "transaction_id" => $transactionId,
      "out_order_no" => $outOrderNo,
      "receivers" => $receivers,
      "unfreeze_unsplit" => $unfreezeUnsplit
    ];

    $JsonBody = json_encode($Body, JSON_UNESCAPED_UNICODE);
    $PrivateKeyFile = file_get_contents($this->PrivateKeyFilePath);
    $MerchantPrivateKey = openssl_pkey_get_private($PrivateKeyFile);
    $Now = time();
    $Nonce = md5($Now);
    $GenerateSignMessage = $HTTPMethod . "\n" .
      "/v3/profitsharing/orders\n" .
      $Now . "\n" .
      $Nonce . "\n" .
      $JsonBody . "\n";
    openssl_sign($GenerateSignMessage, $RawSign, $MerchantPrivateKey, 'sha256WithRSAEncryption');
    $OrderSign = base64_encode($RawSign);

    $AuthorizationContent = sprintf(
      'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
      $this->MerchantId,
      $Nonce,
      $Now,
      $this->PrivateKeySerialNo,
      $OrderSign
    );
    $this->CURL->headers([
      "Authorization" => "WECHATPAY2-SHA256-RSA2048 $AuthorizationContent",
      'Content-Type' => 'application/json; charset=UTF-8',
      'Accept' => 'application/json',
      'User-Agent' => '*/*',
    ]);
    $response = $this->post("profitsharing/orders", $Body, [], false);

    $R = new ReturnResult(true);
    if ($response->errorNo()) {
      return $R->error(500, 500, "服务器错误", $response->error());
    }
    $ResponseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $R->error(500, $response->statusCode() . ":" . $ResponseData['code'], "服务器错误", $ResponseData);
    }

    return $R->success($ResponseData);
  }
  public function generateSign($URI, $HTTPRequestMethod = "POST", $Time, $NonceStr, $BodyJson)
  {
    $PrivateKeyFile = file_get_contents($this->PrivateKeyFilePath);
    $MerchantPrivateKey = openssl_pkey_get_private($PrivateKeyFile);
    $GenerateSignMessage = $HTTPRequestMethod . "\n" .
      "$URI\n" .
      $Time . "\n" .
      $NonceStr . "\n" .
      $BodyJson . "\n";
    openssl_sign($GenerateSignMessage, $RawSign, $MerchantPrivateKey, 'sha256WithRSAEncryption');
    return base64_encode($RawSign);
  }
  public function generateAuthorizationValue($NonceStr, $Time, $Sign)
  {
    return sprintf(
      'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
      $this->MerchantId,
      $NonceStr,
      $Time,
      $this->PrivateKeySerialNo,
      $Sign
    );
  }
  public function addAuthorizationToHeader($NonceStr, $Time, $Sign)
  {
    $this->CURL->headers([
      "Authorization" => "WECHATPAY2-SHA256-RSA2048 " . $this->generateAuthorizationValue($NonceStr, $Time, $Sign),
    ]);
    return $this;
  }
}
