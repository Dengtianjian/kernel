<?php

namespace kernel\Modules\WechatPay\V3\Products;

use kernel\Modules\WechatPay\V3\WechatPayProductApi;

/**
 * 微信支付 - H5 支付
 *
 * 除下单外的一切接口（查单、关单、合单下单）都在父类
 * {@see WechatPayProductApi} 中，退款独立在
 * {@see \kernel\Modules\WechatPay\V3\WechatPayRefundApi}。
 *
 * H5 支付下单只需把返回的 `h5_url` 交给浏览器跳转，不需要再签调起参数，
 * 因此本类不重写任何签名逻辑。
 */
class H5 extends WechatPayProductApi
{
  /**
   * 产品在接口路径中的标识
   *
   * @var string
   */
  protected $product = "h5";

  /**
   * H5 下单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_3_1.shtml
   *
   * 用户从非微信浏览器打开页面、点击支付后跳转微信完成支付；`scene_info` 为必填，
   * 其中的客户端 IP 与场景类型用于风控，传错误值会被微信驳回。
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @param string $payerClientIp 用户客户端 IP（支持 IPv4/IPv6）
   * @param integer $total 订单总金额（分）
   * @param string $description 商品描述
   * @param integer|null $periodSeconds 订单有效期（秒）；为 null 时默认 1 小时
   * @param string $attach 附加数据，原样返回
   * @param string $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否为分账订单
   * @param boolean $supportFapiao 电子发票入口开放标识
   * @param string $currency 货币类型，默认 CNY
   * @param string $h5Type 场景类型：Wap（浏览器）/ iOS / Android / MWEB
   * @return array|boolean 成功时返回含 h5_url（浏览器跳转地址）
   */
  public function order($outTradeNo, $payerClientIp, $total, $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY", $h5Type = "Wap")
  {
    $body = $this->buildOrderBody($outTradeNo, $total, $description, $periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency);
    $body['scene_info'] = [
      "payer_client_ip" => $payerClientIp,
      "h5_info" => [
        "type" => $h5Type
      ]
    ];

    return $this->request("POST", "/v3/pay/transactions/" . $this->product, $body);
  }
}
