<?php

namespace kernel\Discuz\WechatPay\Products;

use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayJsapiOrderTrait;
use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayProductTrait;
use kernel\Discuz\WechatPay\Traits\DiscuzWechatPayQueryTrait;
use kernel\Modules\WechatPay\V3\Products\MiniProgram;

/**
 * 小程序支付
 *
 * 与 {@see DiscuzJSAPI} 共用下单逻辑（微信侧是同一个接口），差别只在客户端调起方式，
 * 因此直接复用 {@see DiscuzWechatPayJsapiOrderTrait}。
 */
class DiscuzMiniProgram extends MiniProgram
{
  use DiscuzWechatPayProductTrait;
  use DiscuzWechatPayJsapiOrderTrait;
  use DiscuzWechatPayQueryTrait;
}
