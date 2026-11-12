<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 合单支付公共接口
 *
 * 合单支付的下单按产品分路径（{@see WechatPayProductApi::combine()}），
 * 但下单之后的查单与关单对所有产品完全一样——只认合单商户订单号，路径里不带产品，
 * 这与单笔支付侧 {@see WechatPayProductApi} 之于 {@see \kernel\Modules\WechatPay\V3\Products\JSAPI}
 * 的分工完全对称。
 *
 * 方法返回约定：成功返回微信响应数据（数组；关单这类无响应体的接口返回 true），失败返回 false，
 * 错误信息见 getError()；判断失败请用 `=== false`，详见 {@see WechatPayV3Api::request()}。
 */
class WechatPayCombineApi extends WechatPayV3Api
{
  /**
   * 合单查询订单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_11.shtml
   *
   * @param string $combineOutTradeNo 合单商户订单号
   * @return array|boolean 成功时返回含 sub_orders、combine_payer_info 等
   */
  public function query($combineOutTradeNo)
  {
    return $this->request("GET", "/v3/combine-transactions/out-trade-no/" . $combineOutTradeNo);
  }

  /**
   * 合单关闭订单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_12.shtml
   *
   * 注意：合单关单会关闭其下**所有**子单，且需在子单全部创建完成后再调用。
   *
   * @param string $combineOutTradeNo 合单商户订单号
   * @return boolean|array 成功返回 true（关单返回 HTTP 204，无响应体）；失败返回 false
   */
  public function close($combineOutTradeNo)
  {
    $response = $this->request("POST", "/v3/combine-transactions/out-trade-no/" . $combineOutTradeNo . "/close", [
      "combine_appid" => $this->merchant->appId(),
      "combine_mchid" => $this->merchant->merchantId()
    ]);
    if ($response === false) {
      return false;
    }

    // 合单关单成功返回 HTTP 204、无响应体，请求方法拿到的数据是 null：操作已完成，返回 true
    return $response === null ? true : $response;
  }
}
