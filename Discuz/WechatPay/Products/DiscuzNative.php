<?php

namespace kernel\Discuz\WechatPay\Products;

use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayProductTrait;
use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayQueryTrait;
use kernel\Modules\WechatPay\V3\Products\Native;

/**
 * Native 支付（扫码）
 *
 * 优先交给 Discuz! 的 `pay_wechat::pay()` 执行；一旦它不可用、被微信拒绝、或参数超出其表达能力，
 * 回落到 kernel 直连。产品形态由 UA 决定是 Discuz! 的固有行为，因此**移动端请求直接不走它**——
 * 那时它只会返回 h5_url，而订单已经建出来了，事后回退会撞上「订单已存在」。
 *
 * 返回值与父类一致：`['code_url' => ...]`。
 */
class DiscuzNative extends Native
{
  use DiscuzWechatPayProductTrait;
  use DiscuzWechatPayQueryTrait;

  /**
   * Native 下单
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @param integer $total 订单总金额（分）
   * @param string $description 商品描述
   * @param integer|null $periodSeconds 订单有效期（秒）
   * @param string $attach 附加数据
   * @param string $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否为分账订单
   * @param boolean $supportFapiao 是否开放电子发票入口
   * @param string $currency 货币类型
   * @return array|boolean 成功返回含 code_url；失败返回 false，错误状态记在本实例上
   */
  public function order($outTradeNo, $total, $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY")
  {
    if (!$this->discuzIsMobile() && $this->discuzAcceptableOrder($periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency)) {
      $url = $this->discuzPayUrl($outTradeNo, $total, $description);

      if (is_string($url)) {
        if (str_starts_with($url, "weixin://")) {
          return ["code_url" => $url];
        }
        //* 订单已建但链接不是 Native 的：不重下单，交由上层查单/关单处理
        return $this->break(500, 500, "Discuz! 返回的支付链接与 Native 产品不符", $url);
      }
      if ($url === false) {
        return false;
      }
    }

    return parent::order($outTradeNo, $total, $description, $periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency);
  }
}
