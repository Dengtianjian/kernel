<?php

namespace kernel\Platform\Wechat\Miniprogram\XPay;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付 - 道具
 *
 * 对应微信「虚拟支付」分类下道具（商品）批量上传与发布相关接口（接口前缀 /xpay）。
 *
 * 说明：本组接口请求体较复杂，统一通过 $data 透传，具体字段以官方文档为准。
 */
class Goods extends WechatMiniProgramApi
{
  /**
   * 批量上传道具（POST /xpay/start_upload_goods）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_start_upload_goods.html
   *
   * 返回任务 id，需再调用 queryUploadGoods() 查询结果。
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含任务 id
   */
  function startUploadGoods($data = [])
  {
    return $this->post("xpay/start_upload_goods", $data)->getData();
  }
  /**
   * 查询批量上传道具任务（POST /xpay/query_upload_goods）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_upload_goods.html
   *
   * @param array $data 请求体字段以官方文档为准（如任务 id）
   * @return array 微信响应数组，成功时含任务状态与结果
   */
  function queryUploadGoods($data = [])
  {
    return $this->post("xpay/query_upload_goods", $data)->getData();
  }
  /**
   * 启动批量发布道具任务（POST /xpay/start_publish_goods）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_start_publish_goods.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含任务 id
   */
  function startPublishGoods($data = [])
  {
    return $this->post("xpay/start_publish_goods", $data)->getData();
  }
  /**
   * 查询批量发布道具任务（POST /xpay/query_publish_goods）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_publish_goods.html
   *
   * @param array $data 请求体字段以官方文档为准（如任务 id）
   * @return array 微信响应数组，成功时含任务状态与结果
   */
  function queryPublishGoods($data = [])
  {
    return $this->post("xpay/query_publish_goods", $data)->getData();
  }
}
