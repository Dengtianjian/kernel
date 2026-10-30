<?php

namespace kernel\Modules\WechatPay\V3\Products;

use kernel\Modules\WechatPay\V3\WechatPayProductApi;

/**
 * 微信支付 - JSAPI 支付（公众号支付）
 *
 * 除下单外的一切接口（查单、关单、合单下单）都在父类 {@see WechatPayProductApi} 中，
 * 本类只实现 JSAPI 独有的下单与调起参数签名；退款独立在
 * {@see \kernel\Modules\WechatPay\V3\WechatPayRefundApi}。
 *
 * 小程序支付（{@see MiniProgram}）与 JSAPI 走的是同一个下单接口，只是客户端调起方式不同，
 * 因此那边直接继承本类。
 */
class JSAPI extends WechatPayProductApi
{
  /**
   * 产品在接口路径中的标识
   *
   * @var string
   */
  protected $product = "jsapi";

  /**
   * JSAPI 下单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_1.shtml
   *
   * 下单成功后本方法会把 prepay_id 进一步签成公众号前端可直接调起支付的参数
   * （`WeixinJSBridge.invoke("getBrandWCPayRequest", params)` / `wx.chooseWXPay`），
   * 因此返回的 data 是**调起参数**而不是微信原始响应。
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @param string $openId 支付者的用户标识（同 appid 下的 openid）
   * @param integer $total 订单总金额（分）
   * @param string $description 商品描述
   * @param integer|null $periodSeconds 订单有效期（秒）；为 null 时默认 1 小时
   * @param string $attach 附加数据，原样返回
   * @param string $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否为分账订单
   * @param boolean $supportFapiao 电子发票入口开放标识
   * @param string $currency 货币类型，默认 CNY
   * @return array|boolean 成功时返回调起参数（timeStamp、nonceStr、package、signType、paySign、prepayId）
   */
  public function order($outTradeNo, $openId, $total, $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY")
  {
    $body = $this->buildOrderBody($outTradeNo, $total, $description, $periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency);
    $body['payer'] = [
      "openid" => $openId
    ];

    $prepay = $this->request("POST", "/v3/pay/transactions/" . $this->product, $body);
    if ($prepay === false) {
      return $this->forwardBreak();
    }

    return $this->payParams($prepay['prepay_id']);
  }

  /**
   * 把 prepay_id 签成 JSAPI/小程序前端的调起参数
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_4.shtml
   *
   * 待签串为 `appId\ntimeStamp\nnonceStr\npackage\n`，其中 package 形如 `prepay_id=xxx`；
   * timeStamp 按微信要求以**字符串**下发（部分前端实现会对数字型时间戳处理不一致）。
   *
   * @param string $prepayId 下单返回的预支付交易会话标识
   * @return array 调起参数
   */
  protected function payParams($prepayId)
  {
    $timeStamp = time();
    $nonceStr = $this->generateNonceString();
    $package = "prepay_id=" . $prepayId;

    return [
      "timeStamp" => (string) $timeStamp,
      "nonceStr" => $nonceStr,
      "package" => $package,
      "signType" => "RSA",
      "paySign" => $this->signMessage($this->merchant->appId() . "\n" . $timeStamp . "\n" . $nonceStr . "\n" . $package . "\n"),
      "prepayId" => $prepayId
    ];
  }
}
