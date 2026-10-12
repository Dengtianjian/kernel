<?php

namespace kernel\Modules\WechatPay\V3\Products;

/**
 * 微信支付 - 小程序支付
 *
 * 小程序支付与 JSAPI 支付走的是**同一个下单接口**（/v3/pay/transactions/jsapi），
 * 调起参数的签名规则也完全一致，差别只在小程序端用 `wx.requestPayment` 调起
 * （不需要 `signType`，但多传不影响）。因此本类直接继承 {@see JSAPI}，
 * 仅用于把「小程序」这一使用场景在代码里显式表达出来。
 */
class MiniProgram extends JSAPI {}
