<?php

namespace kernel\Discuz\WechatPay\Products;

use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayJsapiOrderTrait;
use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayProductTrait;
use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayQueryTrait;
use kernel\Modules\WechatPay\V3\Products\JSAPI;

/**
 * JSAPI 支付（公众号支付）
 *
 * 下单优先交给 Discuz! 的 `pay_wechat::pay_jsapi()`，失败回退 kernel 直连；
 * 拿到 prepay_id 后仍由 kernel 用自己的商户私钥签调起参数（`payParams()`），
 * 返回结构与直连时完全一致。
 */
class DiscuzJSAPI extends JSAPI
{
  use DiscuzWechatPayProductTrait;
  use DiscuzWechatPayJsapiOrderTrait;
  use DiscuzWechatPayQueryTrait;
}
