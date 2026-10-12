<?php

namespace kernel\Modules\WechatPay\V3\Products;

use kernel\Modules\WechatPay\V3\WechatPayProductApi;

/**
 * 微信支付 - APP 支付
 *
 * 除下单外的一切接口（查单、关单、合单下单）都在父类 {@see WechatPayProductApi} 中，
 * 本类只实现 APP 独有的下单与调起参数签名；退款独立在
 * {@see \kernel\Modules\WechatPay\V3\WechatPayRefundApi}。
 *
 * 注意 APP 支付的调起签名规则与 JSAPI **不同**：待签串是 `appId\ntimestamp\nnoncestr\nprepayid\n`
 * （prepayid 不加 `prepay_id=` 前缀），且由**客户端**用相同规则验签后调起微信支付。
 */
class APP extends WechatPayProductApi
{
  /**
   * 产品在接口路径中的标识
   *
   * @var string
   */
  protected $product = "app";

  /**
   * APP 下单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_2_1.shtml
   *
   * 返回的 data 是可直接交给客户端调起支付的参数（`appid/partnerid/prepayid/package/noncestr/timestamp/sign`），
   * 而不是微信原始响应。
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @param integer $total 订单总金额（分）
   * @param string $description 商品描述
   * @param integer|null $periodSeconds 订单有效期（秒）；为 null 时默认 1 小时
   * @param string $attach 附加数据，原样返回
   * @param string $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否为分账订单
   * @param boolean $supportFapiao 电子发票入口开放标识
   * @param string $currency 货币类型，默认 CNY
   * @return array|boolean 成功时返回调起参数（appid、partnerid、prepayid、package、noncestr、timestamp、sign）
   */
  public function order($outTradeNo, $total, $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY")
  {
    $body = $this->buildOrderBody($outTradeNo, $total, $description, $periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency);

    $prepay = $this->request("POST", "/v3/pay/transactions/" . $this->product, $body);
    if ($prepay === false) {
      return false;
    }

    return $this->payParams($prepay['prepay_id']);
  }

  /**
   * 把 prepay_id 签成 APP 客户端的调起参数
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_2_4.shtml
   *
   * 待签串为 `appId\ntimestamp\nnoncestr\nprepayid\n`；timestamp 按微信要求以**字符串**下发。
   *
   * @param string $prepayId 下单返回的预支付交易会话标识
   * @return array 调起参数
   */
  protected function payParams($prepayId)
  {
    $timeStamp = time();
    $nonceStr = $this->generateNonceString();

    return [
      "appid" => $this->merchant->appId(),
      "partnerid" => $this->merchant->merchantId(),
      "prepayid" => $prepayId,
      "package" => "Sign=WXPay",
      "noncestr" => $nonceStr,
      "timestamp" => (string) $timeStamp,
      "sign" => $this->signMessage($this->merchant->appId() . "\n" . $timeStamp . "\n" . $nonceStr . "\n" . $prepayId . "\n")
    ];
  }
}
