<?php

namespace kernel\Discuz\WechatPay\Traits;

/**
 * Discuz! X 的查单（trait，供各产品子类共用）
 *
 * 查单的请求路径不带产品，四个产品对象各自继承本 trait 即可，不必各写一份。
 * 使用本 trait 的类必须同时使用 {@see DiscuzWechatPayProductTrait}（提供 `discuzChannel()`）。
 *
 * ## 返回值还原的取舍
 *
 * Discuz! 的 `status()` 只回两种形态：
 * - 已支付：`['code' => 200, 'data' => ['trade_no' => ..., 'payment_time' => ...]]`
 * - 未支付或查询失败：`['code' => <trade_state 或 HTTP 码>, 'message' => ...]`
 *
 * 因此还原出来的数组**只有** `trade_state` / `transaction_id` / `success_time`，
 * 拿不到 `amount`、`payer`、`trade_state_desc` 之外的信息。依赖金额核对的调用方
 * 不应使用本通道——这也是 `documents` 里“统一接口不等于统一语义”的具体一处。
 */
trait DiscuzWechatPayQueryTrait
{
  /**
   * 查询订单（按商户订单号）
   *
   * 已支付 → 还原成微信原始结构的核心字段；交易状态明确（未支付、已关闭等）→ 原样还原；
   * 其余情况（网络异常、HTTP 错误）→ 回退 kernel 直连。
   *
   * @param string $outTradeNo 商户系统内部订单号
   * @return array|boolean 成功返回含 trade_state 等；失败返回 false，错误状态记在本实例上
   */
  public function queryByOutTradeNo($outTradeNo)
  {
    $channel = $this->discuzChannel();
    if ($channel !== null) {
      $result = $channel->status((string) $outTradeNo);

      if (is_array($result) && isset($result["code"])) {
        if ($result["code"] == 200) {
          //* trade_no 是微信支付订单号；payment_time 是 Discuz! 用 strtotime(success_time) 算出的时间戳
          $paymentTime = $result["data"]["payment_time"] ?? null;

          return [
            "out_trade_no" => (string) $outTradeNo,
            "transaction_id" => (string) ($result["data"]["trade_no"] ?? ""),
            "trade_state" => "SUCCESS",
            "success_time" => $paymentTime ? date("c", (int) $paymentTime) : null,
          ];
        }

        $tradeStates = ["NOTPAY", "CLOSED", "REFUND", "REVOKED", "USERPAYING", "PAYERROR"];
        if (is_string($result["code"]) && in_array($result["code"], $tradeStates, true)) {
          return [
            "out_trade_no" => (string) $outTradeNo,
            "trade_state" => $result["code"],
            "trade_state_desc" => (string) ($result["message"] ?? ""),
          ];
        }
      }
    }

    return parent::queryByOutTradeNo($outTradeNo);
  }
}
