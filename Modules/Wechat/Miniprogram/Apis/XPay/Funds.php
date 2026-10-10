<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\XPay;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付 - 资金与提现
 *
 * 对应微信「虚拟支付」分类下广告金、账户余额、提现相关接口（接口前缀 /xpay）。
 *
 * 说明：本组接口请求体较复杂，统一通过 $data 透传，具体字段以官方文档为准。
 */
class Funds extends WechatMiniProgramApi
{
  /**
   * 查询广告金充值账户（POST /xpay/query_transfer_account）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_transfer_account.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含账户信息
   */
  function queryTransferAccount($data = [])
  {
    return $this->post("xpay/query_transfer_account", $data)->getData();
  }
  /**
   * 查询广告金发放记录（POST /xpay/query_adver_funds）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_adver_funds.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含发放记录
   */
  function queryAdverFunds($data = [])
  {
    return $this->post("xpay/query_adver_funds", $data)->getData();
  }
  /**
   * 充值广告金（POST /xpay/create_funds_bill）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_create_funds_bill.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含充值单信息
   */
  function createFundsBill($data = [])
  {
    return $this->post("xpay/create_funds_bill", $data)->getData();
  }
  /**
   * 绑定广告金充值账户（POST /xpay/bind_transfer_accout）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_bind_transfer_accout.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function bindTransferAccount($data = [])
  {
    return $this->post("xpay/bind_transfer_accout", $data)->getData();
  }
  /**
   * 查询广告金充值记录（POST /xpay/query_funds_bill）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_funds_bill.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含充值记录
   */
  function queryFundsBill($data = [])
  {
    return $this->post("xpay/query_funds_bill", $data)->getData();
  }
  /**
   * 查询广告金回收记录（POST /xpay/query_recover_bill）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_recover_bill.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含回收记录
   */
  function queryRecoverBill($data = [])
  {
    return $this->post("xpay/query_recover_bill", $data)->getData();
  }
  /**
   * 查询商家账户可提现余额（POST /xpay/query_biz_balance）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_biz_balance.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含可提现余额
   */
  function queryBizBalance($data = [])
  {
    return $this->post("xpay/query_biz_balance", $data)->getData();
  }
  /**
   * 创建提现单（POST /xpay/create_withdraw_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_create_withdraw_order.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含提现单信息
   */
  function createWithdrawOrder($data = [])
  {
    return $this->post("xpay/create_withdraw_order", $data)->getData();
  }
  /**
   * 查询提现单（POST /xpay/query_withdraw_order）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_query_withdraw_order.html
   *
   * @param array $data 请求体字段以官方文档为准（如提现单号）
   * @return array 微信响应数组，成功时含提现单状态
   */
  function queryWithdrawOrder($data = [])
  {
    return $this->post("xpay/query_withdraw_order", $data)->getData();
  }
}
