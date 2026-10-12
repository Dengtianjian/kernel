<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 支付产品基类
 *
 * APIv3 的下单接口按支付产品分路径（/v3/pay/transactions/jsapi|app|h5|native），
 * 但**下单之后的接口对所有产品完全一样**：查单、关单只认商户订单号或微信支付订单号，
 * 路径里不带产品。这些共用接口收在本类，
 * 各产品类（{@see \kernel\Modules\WechatPay\V3\Products\JSAPI} 等）继承它、只实现自己差异化的下单。
 *
 * 退款同样与支付产品无关（路径 /v3/refund/domestic/refunds 不带产品），
 * 但已独立成 {@see WechatPayRefundApi}，不在本类。
 *
 * 合单下单同样按产品分路径（/v3/combine-transactions/{产品}），故 {@see combine()} 也在本基类，
 * 由子类声明的 {@see $product} 决定路径；而合单共用的查单/关单不属于任何单一产品，
 * 收在 {@see WechatPayCombineApi}——与「产品下单 / 产品共用接口」的分工一致。
 *
 * 子类必须声明 {@see $product}，并提供自己的下单方法。
 *
 * 方法返回约定：成功返回微信响应数据（数组；关单这类无响应体的接口返回 true），失败返回 false，
 * 错误信息见 getError()；判断失败请用 `=== false`，详见 {@see WechatPayV3Api::request()}。
 */
class WechatPayProductApi extends WechatPayV3Api
{
  /**
   * 产品在接口路径中的标识（jsapi / app / h5 / native），由子类声明
   *
   * @var string|null
   */
  protected $product = null;

  // ===================================================================
  // 跨产品共用接口
  // ===================================================================

  /**
   * 查询订单（按商户订单号）
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_2.shtml
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @return array|boolean 成功时返回含 trade_state、transaction_id、amount 等
   */
  public function queryByOutTradeNo($outTradeNo)
  {
    return $this->request("GET", "/v3/pay/transactions/out-trade-no/" . $outTradeNo, [], [
      "mchid" => $this->merchant->merchantId()
    ]);
  }

  /**
   * 查询订单（按微信支付订单号）
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_2.shtml
   *
   * @param string $transactionId 微信支付订单号
   * @return array|boolean 成功时返回含 trade_state、out_trade_no、amount 等
   */
  public function queryByTransactionId($transactionId)
  {
    return $this->request("GET", "/v3/pay/transactions/id/" . $transactionId, [], [
      "mchid" => $this->merchant->merchantId()
    ]);
  }

  /**
   * 关闭订单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_3.shtml
   *
   * 订单生成后不能马上调用关单接口，最短调用时间间隔为 5 分钟。
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @return boolean|array 成功返回 true（关单返回 HTTP 204，无响应体）；失败返回 false
   */
  public function close($outTradeNo)
  {
    $response = $this->request("POST", "/v3/pay/transactions/out-trade-no/" . $outTradeNo . "/close", [
      "mchid" => $this->merchant->merchantId()
    ]);
    if ($response === false) {
      return false;
    }

    // 关单成功返回 HTTP 204、无响应体，请求方法拿到的数据是 null：操作已完成，返回 true
    return $response === null ? true : $response;
  }

  // ===================================================================
  // 合单下单（同样按产品分路径）
  // ===================================================================

  /**
   * 合单下单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter5_1_1.shtml
   *
   * 一次支付合并多笔子单；子单结构复杂（含每笔子单的商户号、金额、分账标识、优惠等），
   * 由 $subOrders 原样透传，字段以官方文档为准——与微信模块
   * {@see \kernel\Modules\Wechat\Miniprogram\Apis\XPay\Order} 处理复杂报文的做法一致。
   *
   * 调起与单笔下单相同：JSAPI/小程序取返回的 prepay_id 再签名，H5 跳转 h5_url，Native 用 code_url。
   *
   * @param string $combineOutTradeNo 合单商户订单号
   * @param array $subOrders 子单列表，每项为一个子单报文
   * @param integer|null $periodSeconds 订单有效期（秒）；为 null 时默认 1 小时
   * @param string|null $notifyUrl 支付结果回调地址；为 null 时取商户配置的 notifyUrl
   * @return array|boolean 成功时返回含 prepay_id（JSAPI/APP/Native）或 h5_url（H5）
   */
  public function combine($combineOutTradeNo, $subOrders, $periodSeconds = null, $notifyUrl = null)
  {
    return $this->request("POST", "/v3/combine-transactions/" . $this->product, [
      "combine_appid" => $this->merchant->appId(),
      "combine_mchid" => $this->merchant->merchantId(),
      "combine_out_trade_no" => $combineOutTradeNo,
      "sub_orders" => $subOrders,
      "notify_url" => $notifyUrl === null ? $this->merchant->notifyUrl() : $notifyUrl,
      "time_expire" => date("Y-m-d\TH:i:s+08:00", $this->expireTime($periodSeconds))
    ]);
  }

  // ===================================================================
  // 供子类复用的报文组装
  // ===================================================================

  /**
   * 组装下单报文中各产品共有的字段
   *
   * 只写有值的可选项：微信对部分字段（如 goods_tag、attach）传空串的容忍度不一，
   * 与其塞空值不如不传。
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @param integer $total 订单总金额（分）
   * @param string $description 商品描述
   * @param integer|null $periodSeconds 订单有效期（秒）；为 null 时默认 1 小时
   * @param string $attach 附加数据，原样返回
   * @param string $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否为分账订单
   * @param boolean $supportFapiao 电子发票入口开放标识
   * @param string $currency 货币类型，默认 CNY
   * @return array 下单报文（不含各产品自己的字段，如 payer、scene_info）
   */
  protected function buildOrderBody($outTradeNo, $total, $description, $periodSeconds = null, $attach = "", $goodsTag = "", $profitSharing = false, $supportFapiao = false, $currency = "CNY")
  {
    $body = [
      "appid" => $this->merchant->appId(),
      "mchid" => $this->merchant->merchantId(),
      "description" => $description,
      "out_trade_no" => $outTradeNo,
      "notify_url" => $this->merchant->notifyUrl(),
      "amount" => [
        "total" => $total,
        "currency" => $currency
      ],
      "time_expire" => date("Y-m-d\TH:i:s+08:00", $this->expireTime($periodSeconds))
    ];
    if ($attach) {
      $body['attach'] = $attach;
    }
    if ($goodsTag) {
      $body['goods_tag'] = $goodsTag;
    }
    if ($profitSharing) {
      $body['settle_info'] = ["profit_sharing" => true];
    }
    if ($supportFapiao) {
      $body['support_fapiao'] = true;
    }

    return $body;
  }

  /**
   * 计算订单过期时间
   *
   * @param integer|null $periodSeconds 有效期秒数；为 null 时取默认 1 小时
   * @return integer 过期时间戳
   */
  protected function expireTime($periodSeconds = null)
  {
    return $periodSeconds === null ? strtotime("+1 hour") : time() + $periodSeconds;
  }
}
