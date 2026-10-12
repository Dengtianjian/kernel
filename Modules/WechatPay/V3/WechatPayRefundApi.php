<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 退款
 *
 * 对应微信支付的**退款产品**（/v3/refund/domestic/refunds*），官方该产品下共四个接口：
 * {@see apply()} 申请退款 → {@see query()} 查退款结果 → 若状态为 ABNORMAL（原路退款失败，
 * 例如用户银行卡作废 / 冻结）再用 {@see applyAbnormal()} 发起异常退款
 * （退到用户银行卡或商户侧银行账户）；剩余一个是退款结果通知回调，
 * 由申请时传入的 notify_url 接收、报文解密用 {@see WechatPayV3Api::decryptNotifyData()}，
 * 本类不处理回调。
 *
 * 本类从 {@see WechatPayProductApi} 中抽出独立成类：退款请求路径不带支付产品，
 * 任何产品下单的订单都用同一套退款接口（详见 {@see WechatPayProductApi} 的类注释），
 * 支付分订单退款走的也是这两个接口——原订单标识传 transaction_id 即可，
 * 取自查单结果 collection.details（见 {@see WechatPayScoreApi::query()}）。
 *
 * 方法返回约定：成功返回微信响应数据（数组），失败返回 false，错误信息见 getError()；
 * 判断失败请用 `=== false`，详见 {@see WechatPayV3Api::request()}。
 */
class WechatPayRefundApi extends WechatPayV3Api
{
  /**
   * 申请退款（正常退款）
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4013071036
   *
   * 单笔全额退款与多次部分退款都走本接口；金额单位为分，且同一笔订单的多次退款
   * 累计不能超过原订单金额。$transactionId 与 $outTradeNo 二选一（都传时微信以 transaction_id 为准），
   * 都不传会被微信以参数错误驳回，因此这里只在传了值时才写入报文。
   *
   * 交易发生后 1 年内可退，一笔订单最多部分退款 50 次（多次部分退款需换商户退款单号、
   * 间隔 1 分钟再调）；重试务必使用**原商户退款单号**，否则有重复退款风险。
   * 返回成功只代表受理成功，最终结果以退款结果通知或 {@see query()} 为准。
   *
   * $amountFrom 与 $goodsDetail 属低频参数，前者用于多账户出资（仅未开通退款支出分离、
   * 且订单为待分账或分账中的分账订单），后者用于指定商品退款，字段需与下单时的单品列表一致。
   *
   * @param string $outRefundNo 商户退款单号（商户系统内部唯一，只能是数字、大小写字母、`_-|@`）
   * @param integer $refundAmount 退款金额（分），不能超过原订单支付金额
   * @param integer $totalAmount 原订单金额（分）
   * @param string|null $outTradeNo 原商户订单号
   * @param string|null $transactionId 原微信支付订单号
   * @param string $reason 退款原因；部分退款且金额 ≤ 1 元时不在退款消息中体现
   * @param string|null $notifyUrl 退款结果回调地址；为 null 时取商户配置的 notifyUrl
   * @param string|null $fundsAccount 退款资金来源：AVAILABLE（仅旧资金流商户适用，从可用余额出资，
   *                                  否则默认用未结算资金）/ UNSETTLED（仅出行预付押金退款适用）
   * @param array|null $amountFrom 退款出资账户及金额，每项含 account（AVAILABLE 可用余额 /
   *                               UNAVAILABLE 不可用余额）、amount（出资金额，分）；
   *                               各账户之和须等于退款金额、账户类型不可重复
   * @param array|null $goodsDetail 退款商品，每项含 merchant_goods_id、unit_price、refund_amount、
   *                                refund_quantity，以及可选的 wechatpay_goods_id、goods_name
   * @return array|boolean 成功时返回含 refund_id、status（SUCCESS / CLOSED / PROCESSING / ABNORMAL）、
   *                amount、channel、user_received_account、create_time 等
   */
  public function apply($outRefundNo, $refundAmount, $totalAmount, $outTradeNo = null, $transactionId = null, $reason = "", $notifyUrl = null, $fundsAccount = null, $amountFrom = null, $goodsDetail = null)
  {
    $amount = [
      "refund" => $refundAmount,
      "total" => $totalAmount,
      "currency" => "CNY"
    ];
    if ($amountFrom !== null && $amountFrom !== []) {
      $amount['from'] = $amountFrom;
    }
    $body = [
      "out_refund_no" => $outRefundNo,
      "notify_url" => $notifyUrl === null ? $this->merchant->notifyUrl() : $notifyUrl,
      "amount" => $amount
    ];
    if ($outTradeNo !== null && $outTradeNo !== "") {
      $body['out_trade_no'] = $outTradeNo;
    }
    if ($transactionId !== null && $transactionId !== "") {
      $body['transaction_id'] = $transactionId;
    }
    if ($reason !== "") {
      $body['reason'] = $reason;
    }
    if ($fundsAccount !== null && $fundsAccount !== "") {
      $body['funds_account'] = $fundsAccount;
    }
    if ($goodsDetail !== null && $goodsDetail !== []) {
      $body['goods_detail'] = $goodsDetail;
    }

    return $this->request("POST", "/v3/refund/domestic/refunds", $body);
  }

  /**
   * 发起异常退款（正常退款走到 ABNORMAL 后的处理）
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4013071193
   *
   * 退款单状态为 ABNORMAL（原路退款失败，如用户银行卡作废或冻结）时的处理：可退到**用户银行卡**
   * 或退回到**商户侧银行账户**。退到用户时只支持招行、交通、农行、建行、工商、中行、平安、浦发、
   * 中信、光大、民生、兴业、广发、邮储、宁波银行的**借记卡**。
   *
   * bank_account 与 real_name 属敏感信息，微信要求加密后传输——本模块尚未集成平台证书，
   * 加密需由调用方自行完成后再传入（同 {@see WechatPayTransferApi::transfer()} 的 user_name）。
   *
   * @param string $refundId 微信支付退款单号（申请退款时返回的 refund_id，非商户退款单号）
   * @param string $outRefundNo 商户退款单号（申请退款时传入的那个）
   * @param string $type 异常退款处理方式：USER_BANK_CARD（退款到用户银行卡）/
   *                     MERCHANT_BANK_CARD（退款至交易商户银行账户）
   * @param string|null $bankType 开户银行标识，退到用户时必传
   * @param string|null $bankAccount 收款银行卡号（已加密），退到用户时必传
   * @param string|null $realName 收款用户姓名（已加密），退到用户时必传
   * @return array|boolean 成功时返回含 refund_id、out_refund_no、status、amount 等（结构同申请退款）
   */
  public function applyAbnormal($refundId, $outRefundNo, $type, $bankType = null, $bankAccount = null, $realName = null)
  {
    $body = [
      "out_refund_no" => $outRefundNo,
      "type" => $type
    ];
    if ($bankType !== null && $bankType !== "") {
      $body['bank_type'] = $bankType;
    }
    if ($bankAccount !== null && $bankAccount !== "") {
      $body['bank_account'] = $bankAccount;
    }
    if ($realName !== null && $realName !== "") {
      $body['real_name'] = $realName;
    }

    return $this->request("POST", "/v3/refund/domestic/refunds/" . $refundId . "/apply-abnormal-refund", $body);
  }

  /**
   * 查询单笔退款
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4013071041
   *
   * 单号走路径参数，没有 query 参数。建议提交退款申请 1 分钟后再查，之后按 5/10/20/30 分钟
   * 逐步衰减频率；零钱退款一般 5 分钟内到账，银行卡退款一般 1-3 个工作日到账。
   * 查到 status 为 ABNORMAL 时改用 {@see applyAbnormal()} 处理。
   *
   * @param string $outRefundNo 商户退款单号
   * @return array|boolean 成功时返回含 status（SUCCESS / CLOSED / PROCESSING / ABNORMAL）、
   *                amount、success_time、promotion_detail 等
   */
  public function query($outRefundNo)
  {
    return $this->request("GET", "/v3/refund/domestic/refunds/" . $outRefundNo);
  }
}
