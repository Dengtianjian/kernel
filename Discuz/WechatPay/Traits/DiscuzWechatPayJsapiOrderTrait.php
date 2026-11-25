<?php

namespace kernel\Discuz\WechatPay\Traits;

/**
 * Discuz! X 的 JSAPI 下单（trait，JSAPI 与小程序共用）
 *
 * 两者在微信侧走的是同一个下单接口，`pay_jsapi()` 也接受同一个 openid 语义，
 * 因此下单逻辑只写一份；使用本 trait 的类需要继承
 * {@see \kernel\Modules\WechatPay\V3\Products\JSAPI}（或其子类）才能用上 `payParams()`。
 *
 * 签名用的是商户私钥，与 Discuz! 无关，所以拿到 prepay_id 后仍由 kernel 生成调起参数——
 * 这样小程序与公众号拿到的结构与直连时完全一致。
 */
trait DiscuzWechatPayJsapiOrderTrait
{
  /**
   * JSAPI / 小程序下单
   *
   * 其余参数（订单有效期、附加数据、分账、发票、币种）Discuz! 表达不了，
   * 一旦传了非默认值就整单交回 kernel，避免参数被静默丢弃。
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @param string $openId 支付者的用户标识
   * @param integer $total 订单总金额（分）
   * @param string $description 商品描述
   * @param integer|null $periodSeconds 订单有效期（秒）
   * @param string $attach 附加数据
   * @param string $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否为分账订单
   * @param boolean $supportFapiao 是否开放电子发票入口
   * @param string $currency 货币类型
   * @return array|boolean 成功返回调起参数；失败返回 false，错误状态记在本实例上
   */
  public function order($outTradeNo, $openId, $total, $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY")
  {
    if ($this->discuzAcceptableOrder($periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency)) {
      $prepayId = $this->discuzJsapiPrepayId($outTradeNo, $total, $description, $openId);

      if (is_string($prepayId) && $prepayId !== "") {
        return $this->payParams($prepayId);
      }
      if ($prepayId === false) {
        return false;
      }
    }

    return parent::order($outTradeNo, $openId, $total, $description, $periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency);
  }
}
