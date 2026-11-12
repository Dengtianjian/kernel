<?php

namespace kernel\Modules\WechatPay\V3\Products;

use kernel\Modules\WechatPay\V3\WechatPayProductApi;

/**
 * 微信支付 - Native 支付
 *
 * 除下单外的一切接口（查单、关单、合单下单）都在父类
 * {@see WechatPayProductApi} 中，退款独立在
 * {@see \kernel\Modules\WechatPay\V3\WechatPayRefundApi}。
 *
 * Native 支付下单只需把返回的 `code_url` 生成二维码供用户扫码，不需要再签调起参数，
 * 因此本类不重写任何签名逻辑。
 */
class Native extends WechatPayProductApi
{
  /**
   * 产品在接口路径中的标识
   *
   * @var string
   */
  protected $product = "native";

  /**
   * Native 下单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_4_1.shtml
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
   * @return array|boolean 成功时返回含 code_url（二维码链接）
   */
  public function order($outTradeNo, $total, $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY")
  {
    $body = $this->buildOrderBody($outTradeNo, $total, $description, $periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency);

    return $this->request("POST", "/v3/pay/transactions/" . $this->product, $body);
  }
}
