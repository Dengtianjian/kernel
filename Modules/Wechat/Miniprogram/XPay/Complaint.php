<?php

namespace kernel\Platform\Wechat\Miniprogram\XPay;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付 - 投诉处理
 *
 * 对应微信「虚拟支付」分类下用户投诉处理相关接口（接口前缀 /xpay）。
 *
 * 说明：本组接口请求体较复杂，统一通过 $data 透传，具体字段以官方文档为准。
 */
class Complaint extends WechatMiniProgramApi
{
  /**
   * 获取投诉列表（POST /xpay/get_complaint_list）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_get_complaint_list.html
   *
   * @param array $data 请求体字段以官方文档为准（如分页、时间范围等）
   * @return array 微信响应数组，成功时含投诉列表
   */
  function getComplaintList($data = [])
  {
    return $this->post("xpay/get_complaint_list", $data)->getData();
  }
  /**
   * 获取投诉详情（POST /xpay/get_complaint_detail）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_get_complaint_detail.html
   *
   * @param array $data 请求体字段以官方文档为准（如投诉单号）
   * @return array 微信响应数组，成功时含投诉详情
   */
  function getComplaintDetail($data = [])
  {
    return $this->post("xpay/get_complaint_detail", $data)->getData();
  }
  /**
   * 获取协商历史（POST /xpay/get_negotiation_history）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_get_negotiation_history.html
   *
   * @param array $data 请求体字段以官方文档为准（如投诉单号）
   * @return array 微信响应数组，成功时含协商历史列表
   */
  function getNegotiationHistory($data = [])
  {
    return $this->post("xpay/get_negotiation_history", $data)->getData();
  }
  /**
   * 回复用户（POST /xpay/response_complaint）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_response_complaint.html
   *
   * @param array $data 请求体字段以官方文档为准（如投诉单号、回复内容、图片等）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function responseComplaint($data = [])
  {
    return $this->post("xpay/response_complaint", $data)->getData();
  }
  /**
   * 完成投诉处理（POST /xpay/complete_complaint）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_complete_complaint.html
   *
   * @param array $data 请求体字段以官方文档为准（如投诉单号）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function completeComplaint($data = [])
  {
    return $this->post("xpay/complete_complaint", $data)->getData();
  }
  /**
   * 商户被管控原因查询（POST /xpay/query_punishment_reasons）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_punishment_reasons.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含管控原因
   */
  function queryPunishmentReasons($data = [])
  {
    return $this->post("xpay/query_punishment_reasons", $data)->getData();
  }
}
