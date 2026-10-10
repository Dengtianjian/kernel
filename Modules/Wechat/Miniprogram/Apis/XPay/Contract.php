<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\XPay;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付 - 签约与扣款
 *
 * 对应微信「虚拟支付」分类下订阅/签约扣款相关接口（接口前缀 /xpay）。
 *
 * 说明：本组接口请求体较复杂，统一通过 $data 透传，具体字段以官方文档为准。
 */
class Contract extends WechatMiniProgramApi
{
  /**
   * 查询签约关系（POST /xpay/query_subscribe_contract）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_subscribe_contract.html
   *
   * @param array $data 请求体字段以官方文档为准（如 openid、contract_id 等）
   * @return array 微信响应数组，成功时含签约关系信息
   */
  function querySubscribeContract($data = [])
  {
    return $this->post("xpay/query_subscribe_contract", $data)->getData();
  }
  /**
   * 预通知扣款（POST /xpay/send_subscribe_pre_payment）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_send_subscribe_pre_payment.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function sendSubscribePrePayment($data = [])
  {
    return $this->post("xpay/send_subscribe_pre_payment", $data)->getData();
  }
  /**
   * 发起订阅扣款（POST /xpay/submit_subscribe_pay_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_submit_subscribe_pay_order.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含扣款订单信息
   */
  function submitSubscribePayOrder($data = [])
  {
    return $this->post("xpay/submit_subscribe_pay_order", $data)->getData();
  }
  /**
   * 商家解约（POST /xpay/cancel_subscribe_contract）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_cancel_subscribe_contract.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function cancelSubscribeContract($data = [])
  {
    return $this->post("xpay/cancel_subscribe_contract", $data)->getData();
  }
}
