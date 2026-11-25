<?php

namespace kernel\Discuz\WechatPay\Products;

use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayProductTrait;
use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayQueryTrait;
use kernel\Modules\WechatPay\V3\Products\H5;

/**
 * H5 支付（浏览器跳转）
 *
 * 与 {@see DiscuzNative} 相反：Discuz! 只有把请求判为移动端时才会走 H5，
 * 因此**非移动端请求直接不走它**（那时它返回的是 code_url，订单却已建出来）。
 *
 * `scene_info` 里的客户端 IP 与场景类型 Discuz! 自行拼装（取 `$_G['clientip']`、类型固定 `Wap`），
 * kernel 传进来的 `$payerClientIp` 与 `$h5Type` 在这条通道上不生效——若调用方显式指定了非默认的
 * 场景类型，说明它在意风控参数，这种情况整单交回 kernel。
 *
 * 返回值与父类一致：`['h5_url' => ...]`。
 */
class DiscuzH5 extends H5
{
  use DiscuzWechatPayProductTrait;
  use DiscuzWechatPayQueryTrait;

  /**
   * H5 下单
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @param string $payerClientIp 用户客户端 IP
   * @param integer $total 订单总金额（分）
   * @param string $description 商品描述
   * @param integer|null $periodSeconds 订单有效期（秒）
   * @param string $attach 附加数据
   * @param string $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否为分账订单
   * @param boolean $supportFapiao 是否开放电子发票入口
   * @param string $currency 货币类型
   * @param string $h5Type 场景类型
   * @return array|boolean 成功返回含 h5_url；失败返回 false，错误状态记在本实例上
   */
  public function order($outTradeNo, $payerClientIp, $total, $description = "", $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY", $h5Type = "Wap")
  {
    if (
      $this->discuzIsMobile()
      && $h5Type === "Wap"
      && $this->discuzAcceptableOrder($periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency)
    ) {
      $url = $this->discuzPayUrl($outTradeNo, $total, $description);

      if (is_string($url)) {
        if (str_contains($url, "tenpay.com")) {
          return ["h5_url" => $url];
        }
        //* 订单已建但链接不是 H5 的：不重下单，交由上层查单/关单处理
        return $this->break(500, 500, "Discuz! 返回的支付链接与 H5 产品不符", $url);
      }
      if ($url === false) {
        return false;
      }
    }

    return parent::order($outTradeNo, $payerClientIp, $total, $description, $periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency, $h5Type);
  }
}
