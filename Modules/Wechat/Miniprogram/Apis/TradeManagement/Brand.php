<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis\TradeManagement;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 小程序交易管理服务 - 品牌
 *
 * 对应微信「小程序交易管理服务」分类下的品牌申请与状态查询接口。
 */
class Brand extends WechatMiniProgramApi
{
  /**
   * 品牌申请（POST /wxa/sec/famousbrand/apply）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_famousbrandapply.html
   *
   * @param array $data 请求体，字段以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function apply($data = [])
  {
    return $this->post("wxa/sec/famousbrand/apply", $data)->getData();
  }
  /**
   * 小程序品牌申请状态查询（POST /wxa/sec/famousbrand/get_status）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_getfamousbrandapplystatus.html
   *
   * @param array $data 请求体，字段以官方文档为准
   * @return array 微信响应数组，成功时含申请状态
   */
  function getStatus($data = [])
  {
    return $this->post("wxa/sec/famousbrand/get_status", $data)->getData();
  }
}
