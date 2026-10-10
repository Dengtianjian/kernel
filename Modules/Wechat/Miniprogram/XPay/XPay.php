<?php

namespace kernel\Platform\Wechat\Miniprogram\XPay;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付（聚合类）
 *
 * 聚合微信「虚拟支付」分类下的接口，按子域拆分为多个子类（接口前缀均为 /xpay）：
 * - 代币：{@see Currency}
 * - 道具：{@see Goods}
 * - 订单与账单：{@see Order}
 * - 资金与提现：{@see Funds}
 * - 签约与扣款：{@see Contract}
 * - 投诉处理：{@see Complaint}
 * - 文件上传：{@see File}
 *
 * 使用示例：
 *
 * ```php
 * $mp->xPay()->currency()->queryUserBalance($openid, ["env" => 0]);
 * $mp->xPay()->order()->queryWithdrawOrder(["withdraw_no" => $no]);
 * ```
 */
class XPay extends WechatMiniProgramApi
{
  /**
   * 代币相关接口
   *
   * @return Currency
   */
  public function currency()
  {
    return new Currency($this->account);
  }
  /**
   * 道具相关接口
   *
   * @return Goods
   */
  public function goods()
  {
    return new Goods($this->account);
  }
  /**
   * 订单与账单相关接口
   *
   * @return Order
   */
  public function order()
  {
    return new Order($this->account);
  }
  /**
   * 资金与提现相关接口
   *
   * @return Funds
   */
  public function funds()
  {
    return new Funds($this->account);
  }
  /**
   * 签约与扣款相关接口
   *
   * @return Contract
   */
  public function contract()
  {
    return new Contract($this->account);
  }
  /**
   * 投诉处理相关接口
   *
   * @return Complaint
   */
  public function complaint()
  {
    return new Complaint($this->account);
  }
  /**
   * 文件上传相关接口
   *
   * @return File
   */
  public function file()
  {
    return new File($this->account);
  }
}
