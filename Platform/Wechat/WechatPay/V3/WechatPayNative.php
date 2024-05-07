<?php

namespace kernel\Platform\Wechat\WechatPay\V3;

use kernel\Foundation\ReturnResult\ReturnResult;

/**
 * 微信支付 - Native支付
 */
class WechatPayNative extends WechatPayV3
{
  /**
   * Native下单
   * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/direct-jsons/native-prepay.html
   *
   * @param string $OrderId 订单号
   * @param double|int $total 订单金额的总金额。订单总金额，单位为分。
   * @param string $currency 订单金额的货币类型，默认是CNY：人民币
   * @param string $description 商品描述
   * @param int $periodSeconds 订单有效期，单位：秒
   * @param string $attach 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用，实际情况下只有支付完成状态才会返回该字段。
   * @param string $goodsTag 订单优惠标记
   * @param boolean $supportFapiao 电子发票入口开放标识 传入true时，支付成功消息和支付详情页将出现开票入口。需要在微信支付商户平台或微信公众平台开通电子发票功能，传此字段才可生效。true：是，false：否
   * @param boolean $profitSharing 是否为分账订单
   * @return ReturnResult
   */
  public function order($OrderId, $total, $currency = "CNY", $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $supportFapiao = false, $profitSharing = false)
  {
    $OrderTime = time();
    $expireTime = strtotime("+1 hour");
    if (!is_null($periodSeconds)) {
      $expireTime = $OrderTime + $periodSeconds;
    }
    $HTTPMethod = "POST";
    $Body = [
      "appid" => $this->AppId,
      "mchid" => $this->MerchantId,
      "description" => $description,
      "notify_url" => $this->NotifyURL,
      "amount" => [
        "total" => $total,
        "currency" => $currency
      ],
      "out_trade_no" => $OrderId,
      "support_fapiao" => $supportFapiao,
      "goods_tag" => $goodsTag,
      "attach" => $attach,
      "time_expire" => date("Y-m-d\TH:i:s+08:00", $expireTime),
      "settle_info" => [
        "profit_sharing" => $profitSharing
      ]
    ];

    $JsonBody = json_encode($Body, JSON_UNESCAPED_UNICODE);
    $PrivateKeyFile = file_get_contents($this->PrivateKeyFilePath);
    $MerchantPrivateKey = openssl_pkey_get_private($PrivateKeyFile);
    $Nonce = md5(time());
    $GenerateSignMessage = $HTTPMethod . "\n" .
      "/v3/pay/transactions/native\n" .
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
    $response = $this->post("pay/transactions/native", $Body, [], false);
    $R = new ReturnResult(true);
    if ($response->errorNo()) {
      return $R->error(500, 500, "服务器错误", $response->error());
    }
    $ResponseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $R->error(500, $response->statusCode() . ":" . $ResponseData['code'], "服务器错误", $ResponseData);
    }
    $CodeURL = $ResponseData['code_url'];

    $Body['params'] = [
      "timeStamp" => $OrderTime,
      "nonceStr" => $Nonce,
      "codeURL" => $CodeURL
    ];

    return $R->success($Body);
  }
}
