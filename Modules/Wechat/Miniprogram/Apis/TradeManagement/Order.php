<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis\TradeManagement;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 小程序交易管理服务 - 订单
 *
 * 对应微信「小程序交易管理服务」分类下的发货信息录入、订单查询等接口。
 */
class Order extends WechatMiniProgramApi
{
  /**
   * 发货信息录入（POST /wxa/sec/order/upload_shipping_info）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_uploadshippinginfo.html
   *
   * @param array $orderKey 订单信息，形如
   *                        ["order_number_type" => 2, "mchid" => "商户号", "out_trade_no" => "商户订单号"]
   *                        或 ["order_number_type" => 1, "transaction_id" => "微信支付订单号"]
   * @param int $logisticsType 物流模式：1 实体物流；2 虚拟商品；3 用户自提；4 同城配送
   * @param array $shippingList 物流信息列表，元素形如
   *                            ["tracking_no" => "物流单号", "express_company" => "物流公司编码", "item_desc" => "商品描述"]
   * @param int $deliveryMode 发货模式：1 统一发货；2 分拆发货
   * @param bool $isAllDelivered 是否已全部发货（分拆发货模式下必填）
   * @param int|null $uploadTime 发货时间（Unix 时间戳，秒），不传时由微信取当前时间
   * @param array|null $payer 支付者信息，形如 ["openid" => "用户 openid"]
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function uploadShippingInfo($orderKey, $logisticsType, $shippingList = [], $deliveryMode = 1, $isAllDelivered = true, $uploadTime = null, $payer = null)
  {
    $body = [
      "order_key" => $orderKey,
      "logistics_type" => $logisticsType,
      "delivery_mode" => $deliveryMode,
      "is_all_delivered" => $isAllDelivered,
      "shipping_list" => $shippingList
    ];
    if ($uploadTime !== null) {
      $body['upload_time'] = $uploadTime;
    }
    if ($payer !== null) {
      $body['payer'] = $payer;
    }

    return $this->post("wxa/sec/order/upload_shipping_info", $body)->getData();
  }
  /**
   * 发货信息合单录入（POST /wxa/sec/order/upload_combined_shipping_info）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_uploadcombinedshippinginfo.html
   *
   * @param array $data 请求体，字段（如 order_key、combined_shipping_list 等）以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function uploadCombinedShippingInfo($data = [])
  {
    return $this->post("wxa/sec/order/upload_combined_shipping_info", $data)->getData();
  }
  /**
   * 查询订单发货状态（POST /wxa/sec/order/get_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_getorder.html
   *
   * 三种查询方式均可单独使用：微信支付单号 order_id、transaction_id，或 mchid + out_trade_no。
   *
   * @param string|null $orderId 微信支付单号
   * @param string|null $transactionId 微信支付订单号
   * @param string|null $outTradeNo 商户订单号（需同时传 $mchid）
   * @param string|null $mchid 微信支付商户号
   * @return array 微信响应数组，成功时含 order 信息
   */
  function getOrder($orderId = null, $transactionId = null, $outTradeNo = null, $mchid = null)
  {
    $body = [];
    if ($orderId !== null) {
      $body['order_id'] = $orderId;
    }
    if ($transactionId !== null) {
      $body['transaction_id'] = $transactionId;
    }
    if ($outTradeNo !== null) {
      $body['out_trade_no'] = $outTradeNo;
    }
    if ($mchid !== null) {
      $body['mchid'] = $mchid;
    }

    return $this->post("wxa/sec/order/get_order", $body)->getData();
  }
  /**
   * 查询订单列表（POST /wxa/sec/order/get_order_list）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_getorderlist.html
   *
   * @param array $data 请求体，字段（如 order_state、order_time_range、page_size、last_index 等）以官方文档为准
   * @return array 微信响应数组，成功时含 order_list
   */
  function getOrderList($data = [])
  {
    return $this->post("wxa/sec/order/get_order_list", $data)->getData();
  }
  /**
   * 确认收货提醒（POST /wxa/sec/order/notify_confirm_receive）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_notifyconfirmreceive.html
   *
   * @param string|null $transactionId 微信支付订单号
   * @param string|null $merchantId 商户号（与 $subMerchantId、$outTradeNo 一并使用）
   * @param string|null $subMerchantId 子商户号（服务商模式）
   * @param string|null $outTradeNo 商户订单号
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function notifyConfirmReceive($transactionId = null, $merchantId = null, $subMerchantId = null, $outTradeNo = null)
  {
    $body = [];
    if ($transactionId !== null) {
      $body['transaction_id'] = $transactionId;
    }
    if ($merchantId !== null) {
      $body['merchant_id'] = $merchantId;
    }
    if ($subMerchantId !== null) {
      $body['sub_merchant_id'] = $subMerchantId;
    }
    if ($outTradeNo !== null) {
      $body['out_trade_no'] = $outTradeNo;
    }

    return $this->post("wxa/sec/order/notify_confirm_receive", $body)->getData();
  }
  /**
   * 消息跳转路径设置（POST /wxa/sec/order/set_msg_jump_path）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_setmsgjumppath.html
   *
   * @param string $path 小程序内跳转路径（需已发布）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function setMsgJumpPath($path)
  {
    return $this->post("wxa/sec/order/set_msg_jump_path", [
      "path" => $path
    ])->getData();
  }
  /**
   * 查询小程序是否已开通发货信息管理服务（POST /wxa/sec/order/is_trade_managed）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_istrademanaged.html
   *
   * @return array 微信响应数组，成功时含 is_trade_managed
   */
  function isTradeManaged()
  {
    return $this->post("wxa/sec/order/is_trade_managed", [])->getData();
  }
  /**
   * 查询小程序是否已完成交易结算管理确认（POST /wxa/sec/order/is_trade_management_confirmation_completed）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_istrademanagementconfirmationcompleted.html
   *
   * @return array 微信响应数组，成功时含是否已完成确认的结果
   */
  function isTradeManagementConfirmationCompleted()
  {
    return $this->post("wxa/sec/order/is_trade_management_confirmation_completed", [])->getData();
  }
  /**
   * 特殊发货报备（POST /wxa/sec/order/opspecialorder）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_opspecialorder.html
   *
   * @param array $data 请求体，字段以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function opSpecialOrder($data = [])
  {
    return $this->post("wxa/sec/order/opspecialorder", $data)->getData();
  }
  /**
   * 小程序交易类型变更申请（POST /wxa/sec/order/setwxatradetypecgi）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/order_shipping/api_setwxatradetypecgi.html
   *
   * @param array $data 请求体，字段以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function setWxaTradeTypeCgi($data = [])
  {
    return $this->post("wxa/sec/order/setwxatradetypecgi", $data)->getData();
  }
}
