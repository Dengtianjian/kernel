<?php

namespace kernel\Discuz\WechatPay;

use pay_wechat;

/**
 * Discuz! X 微信支付通道（请求由 Discuz! 的支付实现执行）
 *
 * 继承 Discuz! 自带的 {@see pay_wechat}，只做**一处**改动：把回调地址从 Discuz! 写死的
 * `api/payment/notify/notify_wechat.php` 换成调用方指定的地址。
 *
 * 为什么必须改：那个脚本收到通知后只会调 `payment::finish_order()` 去写
 * `common_payment_order`，订单不在那张表里就记一条 `paymentlog` 完事、仍然回 SUCCESS。
 * 不改的话，本模块自己的订单永远收不到支付结果。
 *
 * `$notify_url` 在 {@see \pay_base} 里是 public 属性，`pay_wechat` 的下单方法全部读它，
 * 因此构造后改一次即对 native/h5/jsapi 全部生效。
 *
 * 两点使用限制（由 Discuz! 的实现决定，不是本类加的）：
 * - **产品不可指定**：`pay()` 内部按 `weixin::in_event()` / `checkmobile()` 选 h5 或 native，
 *   具体的 `v3_wechat_native_pay()` 等都是 private，子类调不到。要 native 就必须让请求的
 *   UA 不与移动端特征匹配；拿到结果后请校验键名（`code_url` / `h5_url` / `prepay_id`）。
 * - **API 版本由站点决定**：`ec_wechat.ec_wechat_version` 为 0 时走的是 V2（XML + MD5，
 *   且不支持退款），调用方需自检后再决定是否使用本通道。
 *
 * 传 `null` 表示沿用 Discuz! 的默认回调地址——仅用于本地联调，生产必须传入自己的地址。
 */
class DiscuzWechatPayChannel extends pay_wechat
{
  /**
   * 初始化通道
   *
   * 先走父类构造（读取 `ec_wechat` 设置、装配默认回调地址），再按需覆写回调地址。
   *
   * @param string|null $notifyUrl 支付结果通知地址；null 或空串表示沿用 Discuz! 的默认地址
   */
  public function __construct($notifyUrl = null)
  {
    parent::__construct();

    if ($notifyUrl !== null && $notifyUrl !== "") {
      $this->notify_url = $notifyUrl;
    }
  }
}
