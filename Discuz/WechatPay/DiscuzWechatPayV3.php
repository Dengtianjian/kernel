<?php

namespace kernel\Discuz\WechatPay;

use kernel\Discuz\WechatPay\Products\DiscuzH5;
use kernel\Discuz\WechatPay\Products\DiscuzJSAPI;
use kernel\Discuz\WechatPay\Products\DiscuzMiniProgram;
use kernel\Discuz\WechatPay\Products\DiscuzNative;
use kernel\Modules\WechatPay\V3\WechatPayV3;

/**
 * Discuz! X 环境的 APIv3 聚合入口
 *
 * 只替换四个**下单**入口，其余全部沿用父类：
 *
 * - 覆盖：`native()` / `h5()` / `jsapi()` / `miniProgram()`
 *   —— 各产品对象先走 {@see DiscuzWechatPayChannel}，不可用或明确被拒时回退直连
 * - 不覆盖：`refund()`（Discuz! 的退款应答缺 `refund_id`，退款与退款查询一律直连）、
 *   `combine()` / `bill()` / `profitSharing()` / `transfer()` / `score()` / `complaint()`
 *   —— Discuz! 无对应能力；`app()` 同理（APP 支付 Discuz! 没有实现）
 *
 * 关单 `close()` 在产品对象上、查单 `queryByOutTradeNo()` 由产品子类覆盖，二者不在本类。
 *
 * 之所以在**产品方法层**而不是请求层做分流：`WechatPayV3Api::request()` 属于核心模块，
 * 不改它才能让独立部署与 Discuz! 部署共用同一份业务代码。
 *
 * 注意 `WechatPayV3::app()` 与 `BaseObject::app()` 同名（前者是 APP 支付产品入口），
 * 本类不参与应用归属解析，新增方法时不要再用 `app` 作名字。
 */
class DiscuzWechatPayV3 extends WechatPayV3
{
  /**
   * Native 支付（扫码）
   *
   * @return DiscuzNative
   */
  public function native()
  {
    return new DiscuzNative($this->merchant);
  }

  /**
   * H5 支付（浏览器跳转）
   *
   * @return DiscuzH5
   */
  public function h5()
  {
    return new DiscuzH5($this->merchant);
  }

  /**
   * JSAPI 支付（公众号支付）
   *
   * @return DiscuzJSAPI
   */
  public function jsapi()
  {
    return new DiscuzJSAPI($this->merchant);
  }

  /**
   * 小程序支付
   *
   * @return DiscuzMiniProgram
   */
  public function miniProgram()
  {
    return new DiscuzMiniProgram($this->merchant);
  }
}
