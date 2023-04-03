<?php

namespace kernel\Platform\Wechat\WechatPay;

use kernel\Foundation\Date;
use kernel\Foundation\File;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Platform\Wechat\Wechat;

class WechatPayJSApi extends Wechat
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
   * 商户ID
   *
   * @var string
   */
  protected $MerchantId = null;
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
  function __construct($AppId, $MerchantId, $ApiV3Secret, $PrivateKeyFilePath, $PrivateKeySerialNo)
  {
    $this->MerchantId = $MerchantId;
    $this->ApiV3Secret = $ApiV3Secret;
    $this->PrivateKeyFilePath = $PrivateKeyFilePath;
    $this->PrivateKeySerialNo = $PrivateKeySerialNo;

    parent::__construct(null, $AppId);

    $this->CURL->json(true);
  }
  private function gererateTradeNo()
  {
    $now = (string)time();
    $s = str_split($now);
    $nums = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
    $no = [date("YmdHis"), 0];
    for ($i = 0; $i < 1; $i++) {
      for ($i = 0; $i < count($s); $i++) {
        array_push($no, $s[mt_rand(0, 9)]);
      }
    }

    $rand = (string)mt_rand(10000, 99999);
    $before = substr($rand, 0, 2);
    $after = substr($rand, 2);
    array_unshift($no, $before);
    array_push($no, $after);

    return implode("", $no);
  }
  public function order($openId, $notifyURL, $total, $currency = "CNY", $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $supportFapiao = false)
  {
    $OrderTime = time();
    $expireTime = null;
    if (!is_null($periodSeconds)) {
      $expireTime = $OrderTime + $periodSeconds;
    }
    $HTTPMethod = "POST";
    $TradeNo = $this->gererateTradeNo();
    $Body = [
      "appid" => $this->AppId,
      "mchid" => $this->MerchantId,
      "description" => $description,
      "notify_url" => $notifyURL,
      "amount" => [
        "total" => $total,
        "currency" => $currency
      ],
      "payer" => [
        "openid" => $openId
      ],
      "out_trade_no" => $TradeNo,
      "support_fapiao" => $supportFapiao,
      "goods_tag" => $goodsTag,
      "attach" => $attach,
      "time_expire" => date("Y-m-d\TH:i:sT:00", $expireTime)
    ];

    $JsonBody = json_encode($Body);
    $PrivateKeyFile = file_get_contents($this->PrivateKeyFilePath);
    $MerchantPrivateKey = openssl_pkey_get_private($PrivateKeyFile);
    $Nonce = md5(time());
    $GenerateSignMessage = $HTTPMethod . "\n" .
      "/v3/pay/transactions/jsapi\n" .
      $OrderTime . "\n" .
      $Nonce . "\n" .
      $JsonBody . "\n";
    openssl_sign($GenerateSignMessage, $RawSign, $MerchantPrivateKey, 'sha256WithRSAEncryption');
    $OrderSign = base64_encode($RawSign);

    $AuthorizationContent = sprintf(
      'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
      $this->MerchantId,
      $Nonce,
      $OrderTime,
      $this->PrivateKeySerialNo,
      $OrderSign
    );
    $this->CURL->headers([
      "Authorization" => "WECHATPAY2-SHA256-RSA2048 $AuthorizationContent",
      'Content-Type' => 'application/json; charset=UTF-8',
      'Accept' => 'application/json',
      'User-Agent' => '*/*',
    ]);
    $response = $this->post("pay/transactions/jsapi", $Body, [], false);
    $R = new ReturnResult(true);
    if ($response->errorNo()) {
      return $R->error(500, 500, "服务器错误", $response->error());
    }
    $ResponseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $R->error(500, $response->statusCode() . ":" . $ResponseData['code'], "服务器错误", $ResponseData);
    }
    $PrepayId = $ResponseData['prepay_id'];
    $GeneratePaySignMessage = $this->AppId . "\n$OrderTime\n$Nonce\nprepay_id=$PrepayId\n";
    openssl_sign($GeneratePaySignMessage, $PaySign, $MerchantPrivateKey, 'sha256WithRSAEncryption');

    return [
      "timeStamp" => $OrderTime,
      "nonceStr" => $Nonce,
      "paySign" => base64_encode($PaySign),
      "prepayId" => $PrepayId
    ];
  }
}
