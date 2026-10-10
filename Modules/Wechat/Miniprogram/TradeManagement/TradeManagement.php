<?php

namespace kernel\Platform\Wechat\Miniprogram\TradeManagement;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 小程序交易管理服务（聚合类）
 *
 * 聚合微信「小程序交易管理服务」分类下的接口：
 * - 订单与发货：{@see Order}
 * - 品牌：{@see Brand}
 *
 * 使用示例：
 *
 * ```php
 * $mp->tradeManagement()->order()->getOrder(null, $transactionId);
 * $mp->tradeManagement()->brand()->getStatus();
 * ```
 */
class TradeManagement extends WechatMiniProgramApi
{
  /**
   * 订单与发货相关接口
   *
   * @return Order
   */
  public function order()
  {
    return new Order($this->account);
  }
  /**
   * 品牌相关接口
   *
   * @return Brand
   */
  public function brand()
  {
    return new Brand($this->account);
  }
}
