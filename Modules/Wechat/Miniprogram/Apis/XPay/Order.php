<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\XPay;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付 - 订单与账单
 *
 * 对应微信「虚拟支付」分类下订单退款、账单下载相关接口（接口前缀 /xpay）。
 *
 * 说明：本组接口请求体较复杂，统一通过 $data 透传，具体字段以官方文档为准。
 */
class Order extends WechatMiniProgramApi
{
  /**
   * 下载小程序账单（POST /xpay/download_bill）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_download_bill.html
   *
   * @param array $data 请求体字段以官方文档为准（如账单日期等）
   * @return array 微信响应数组，成功时含账单下载地址
   */
  function downloadBill($data = [])
  {
    return $this->post("xpay/download_bill", $data)->getData();
  }
  /**
   * 启动订单退款任务（POST /xpay/refund_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_refund_order.html
   *
   * @param array $data 请求体字段以官方文档为准（如订单号、退款金额等）
   * @return array 微信响应数组，成功时含退款任务信息
   */
  function refundOrder($data = [])
  {
    return $this->post("xpay/refund_order", $data)->getData();
  }
  /**
   * 下载支付订单（POST /xpay/start_download_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_start_download_order.html
   *
   * 返回任务 id，需再调用 queryDownloadOrder() 查询结果。
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含任务 id
   */
  function startDownloadOrder($data = [])
  {
    return $this->post("xpay/start_download_order", $data)->getData();
  }
  /**
   * 查询下载订单任务（POST /xpay/query_download_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_download_order.html
   *
   * @param array $data 请求体字段以官方文档为准（如任务 id）
   * @return array 微信响应数组，成功时含下载地址
   */
  function queryDownloadOrder($data = [])
  {
    return $this->post("xpay/query_download_order", $data)->getData();
  }
  /**
   * 下载虚拟支付 ios 月结账单（POST /xpay/download_ios_settlement_bill）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_download_ios_settlement_bill.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含账单下载地址
   */
  function downloadIosSettlementBill($data = [])
  {
    return $this->post("xpay/download_ios_settlement_bill", $data)->getData();
  }
  /**
   * 下载广告金对应的商户订单信息（POST /xpay/download_adverfunds_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_download_adverfunds_order.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含订单信息下载地址
   */
  function downloadAdverfundsOrder($data = [])
  {
    return $this->post("xpay/download_adverfunds_order", $data)->getData();
  }
}
