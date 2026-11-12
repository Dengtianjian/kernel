<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis\XPay;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付 - 代币
 *
 * 对应微信「虚拟支付」分类下与代币（游戏币/虚拟币）相关的接口（接口前缀 /xpay）。
 *
 * 说明：$extra 用于透传接口的附加字段（如 env、user_ip、appid 等），具体以官方文档为准。
 */
class Currency extends WechatMiniProgramApi
{
  /**
   * 查询代币余额（POST /xpay/query_user_balance）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_user_balance.html
   *
   * @param string $openid 用户 openid
   * @param array $extra 附加参数，如 ["env" => 0, "user_ip" => "1.2.3.4"]
   * @return array 微信响应数组，成功时含余额信息
   */
  function queryUserBalance($openid, $extra = [])
  {
    return $this->post("xpay/query_user_balance", array_merge([
      "openid" => $openid
    ], $extra))->getData();
  }
  /**
   * 扣减代币（POST /xpay/currency_pay）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_currency_pay.html
   *
   * @param string $openid 用户 openid
   * @param int $amount 扣减数量
   * @param string $billNo 业务订单号，需保证唯一
   * @param array $extra 附加参数，如 ["env" => 0]
   * @return array 微信响应数组，成功时含订单信息
   */
  function currencyPay($openid, $amount, $billNo, $extra = [])
  {
    return $this->post("xpay/currency_pay", array_merge([
      "openid" => $openid,
      "amount" => $amount,
      "bill_no" => $billNo
    ], $extra))->getData();
  }
  /**
   * 查询创建的订单（POST /xpay/query_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_order.html
   *
   * @param string $openid 用户 openid
   * @param string $billNo 业务订单号
   * @param array $extra 附加参数，以官方文档为准
   * @return array 微信响应数组，成功时含订单详情
   */
  function queryOrder($openid, $billNo, $extra = [])
  {
    return $this->post("xpay/query_order", array_merge([
      "openid" => $openid,
      "bill_no" => $billNo
    ], $extra))->getData();
  }
  /**
   * 代币支付退款（POST /xpay/cancel_currency_pay）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_cancel_currency_pay.html
   *
   * @param string $openid 用户 openid
   * @param string $billNo 业务订单号
   * @param array $extra 附加参数，以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function cancelCurrencyPay($openid, $billNo, $extra = [])
  {
    return $this->post("xpay/cancel_currency_pay", array_merge([
      "openid" => $openid,
      "bill_no" => $billNo
    ], $extra))->getData();
  }
  /**
   * 代币赠送（POST /xpay/present_currency）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_present_currency.html
   *
   * @param string $openid 用户 openid
   * @param int $amount 赠送数量
   * @param string $billNo 业务订单号，需保证唯一
   * @param array $extra 附加参数，以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function presentCurrency($openid, $amount, $billNo, $extra = [])
  {
    return $this->post("xpay/present_currency", array_merge([
      "openid" => $openid,
      "amount" => $amount,
      "bill_no" => $billNo
    ], $extra))->getData();
  }
  /**
   * 通知已发货完成（POST /xpay/notify_provide_goods）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_notify_provide_goods.html
   *
   * @param array $data 请求体字段以官方文档为准（如 openid、bill_no、order_id 等）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function notifyProvideGoods($data = [])
  {
    return $this->post("xpay/notify_provide_goods", $data)->getData();
  }
}
