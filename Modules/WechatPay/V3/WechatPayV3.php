<?php

namespace kernel\Modules\WechatPay\V3;

use kernel\Modules\WechatPay\V3\Products\APP;
use kernel\Modules\WechatPay\V3\Products\H5;
use kernel\Modules\WechatPay\V3\Products\JSAPI;
use kernel\Modules\WechatPay\V3\Products\MiniProgram;
use kernel\Modules\WechatPay\V3\Products\Native;

/**
 * 微信支付 APIv3（聚合入口）
 *
 * 自身即 V3 请求基类（{@see WechatPayV3Api}），同时是所有 V3 接口对象的取用入口：
 * 每个方法返回对应产品或领域的接口对象（按需实例化，每次调用返回新实例），
 * 与微信模块 {@see \kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccount} 的聚合方式一致。
 *
 * 已聚合的入口：
 * - 支付产品：jsapi()、app()、h5()、native()、miniProgram()
 *   —— 各产品对象继承 {@see WechatPayProductApi}，既有本产品下单，也有查单/关单等通用接口
 * - 合单：combine() —— 合单查单/关单；合单下单在各产品对象上（{@see WechatPayProductApi::combine()}）
 * - 账单：bill()
 * - 退款：refund() —— 与支付产品无关，独立成 {@see WechatPayRefundApi}
 * - 分账：profitSharing()
 * - 商家转账：transfer()
 * - 微信支付分：score()
 * - 消费者投诉：complaint()
 *
 * 商户身份与凭证由 {@see \kernel\Modules\WechatPay\WechatPayMerchant} 在构造时注入，
 * 例如 `new WechatPayV3($merchant)`。
 *
 * 使用示例：
 *
 * ```php
 * $pay = new WechatPayV3($merchant);
 *
 * $jsapi = $pay->jsapi();                        // 保留实例：失败时错误信息记在它身上
 * $params = $jsapi->order($outTradeNo, $openId, 100, "测试商品"); // 成功=调起参数，失败=false
 * if ($params === false) {
 *   $jsapi->getErrorMessage();
 * }
 *
 * $jsapi->queryByOutTradeNo($outTradeNo);        // 查单
 * $pay->refund()->apply($outRefundNo, 100, 100, $outTradeNo);  // 申请退款
 * $pay->combine()->query($combineOutTradeNo);    // 合单查单
 * $pay->bill()->tradeBill("2026-10-01");         // 申请交易账单
 * $pay->profitSharing()->order($transactionId, $outOrderNo, $receivers);
 * $pay->transfer()->transfer($outBillNo, $openId, 100, "新会员奖励", "1001");
 * $pay->score()->order($outOrderNo, $serviceId, "充电宝租借", $riskFund);
 * $pay->complaint()->queryComplaints("2026-10-01", "2026-10-11");
 * ```
 */
class WechatPayV3 extends WechatPayV3Api
{
  /**
   * JSAPI 支付（公众号支付）
   *
   * @return JSAPI
   */
  public function jsapi()
  {
    return new JSAPI($this->merchant);
  }
  /**
   * APP 支付
   *
   * @return APP
   */
  public function app()
  {
    return new APP($this->merchant);
  }
  /**
   * H5 支付
   *
   * @return H5
   */
  public function h5()
  {
    return new H5($this->merchant);
  }
  /**
   * Native 支付
   *
   * @return Native
   */
  public function native()
  {
    return new Native($this->merchant);
  }
  /**
   * 小程序支付
   *
   * 与 JSAPI 是同一个下单接口，差别只在客户端调起方式，见 {@see MiniProgram}。
   *
   * @return MiniProgram
   */
  public function miniProgram()
  {
    return new MiniProgram($this->merchant);
  }
  /**
   * 合单支付（合单查询订单、合单关闭订单）
   *
   * 合单下单不在这里，在各产品对象上（如 `$pay->jsapi()->combine(...)`）。
   *
   * @return WechatPayCombineApi
   */
  public function combine()
  {
    return new WechatPayCombineApi($this->merchant);
  }
  /**
   * 账单（申请交易账单、申请资金账单、下载账单）
   *
   * @return WechatPayBillApi
   */
  public function bill()
  {
    return new WechatPayBillApi($this->merchant);
  }
  /**
   * 退款（申请退款、查询单笔退款、发起异常退款）
   *
   * 退款与支付产品无关：请求路径不带产品，任何产品下单的订单都用同一套接口，
   * 支付分订单退款走的也是它，故不挂在各产品对象上。
   *
   * @return WechatPayRefundApi
   */
  public function refund()
  {
    return new WechatPayRefundApi($this->merchant);
  }
  /**
   * 分账
   *
   * @return WechatPayProfitSharingApi
   */
  public function profitSharing()
  {
    return new WechatPayProfitSharingApi($this->merchant);
  }
  /**
   * 商家转账（新版）
   *
   * @return WechatPayTransferApi
   */
  public function transfer()
  {
    return new WechatPayTransferApi($this->merchant);
  }
  /**
   * 微信支付分（服务订单创建、完结、查单、催收扣款等）
   *
   * @return WechatPayScoreApi
   */
  public function score()
  {
    return new WechatPayScoreApi($this->merchant);
  }
  /**
   * 消费者投诉（消费者投诉 2.0：查询、回复、反馈处理完成、回调地址管理等）
   *
   * @return WechatPayComplaintApi
   */
  public function complaint()
  {
    return new WechatPayComplaintApi($this->merchant);
  }
}
