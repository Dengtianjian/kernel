<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 微信支付分
 *
 * 对应微信支付的**微信支付分**（/v3/payscore/*）服务订单流程：
 * 创建订单 → 前端用创单返回的 package 拉起支付分确认订单页（用户确认后订单进入进行中）
 * → 服务结束完结订单（订单转入待扣款）→ 由支付分自动扣款或 {@see pay()} 催收扣款；
 * 订单进行中还可 {@see cancel()} 取消、{@see modify()} 下调金额，
 * 用户改走其他渠道支付后用 {@see sync()} 同步订单状态。
 *
 * 前提：需先在商户平台开通支付分产品权限、拿到服务 ID（service_id），
 * 并把商户号与 appid 绑定；订单金额与 risk_fund 受「服务 ID 风险金额上限」与服务模式
 * （先免 / 先享）约束，报文字段须按行业规程传参。
 *
 * 两个回调（确认订单回调通知、支付成功回调通知）由创单时的 notify_url 接收，
 * 报文解密用 {@see WechatPayV3Api::decryptNotifyData()}，本类不处理回调。
 *
 * 退款：微信官方把它挂在支付分 API 列表下，但请求路径就是**通用退款接口**，
 * 统一由 {@see WechatPayRefundApi} 承担——支付分的原订单标识传 transaction_id 即可，
 * 取自 {@see query()} 结果 collection.details 里的微信支付交易单号。
 *
 * 方法返回约定：成功返回微信响应数据（数组），失败返回 false，错误信息见 getError()；
 * 判断失败请用 `=== false`，详见 {@see WechatPayV3Api::request()}。
 */
class WechatPayScoreApi extends WechatPayV3Api
{
  /**
   * 创建支付分订单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012587900
   *
   * 创单成功后订单 state 为 CREATED，须把返回的 package 交给前端调起支付分确认订单页；
   * 支持原参重入（相同参数重复调用返回成功），故网络异常时可用原参数重试。
   *
   * risk_fund.name 受模式约束：先免模式只能传 DEPOSIT（押金）/ ADVANCE（预付款）/
   * CASH_DEPOSIT（保证金），先享模式只能传 ESTIMATE_ORDER_COST（订单预估费用）；
   * amount 必须大于 0，且不超过服务 ID 的风险金额上限。
   *
   * @param string $outOrderNo 商户服务订单号，商户号下唯一
   * @param string $serviceId 服务 ID（32 位数字，支付分权限审核通过后由微信支付运营提供）
   * @param string $serviceIntroduction 服务信息，展示给用户，不超过 20 字符
   * @param array $riskFund 服务风险金，含 name（风险名称）、amount（风险金额，分）、description（可选）
   * @param array|null $postPayments 后付费项目，每项含 name、amount（分）、description、count
   * @param array|null $timeRange 服务时间段，含 start_time（支持 OnAccept，表示以用户确认订单时间为开始时间）、
   *                               end_time 与两端备注；精度到日须不早于创单当天，精度到秒须晚于创单时间
   * @param array|null $postDiscounts 商户优惠，每项含 name、description、amount（分）、count
   * @param array|null $location 服务位置，含 start_location、end_location
   * @param string|null $notifyUrl 确认订单回调与支付成功回调共用的地址；为 null 时取商户配置的 notifyUrl
   * @param string|null $attach 商户数据包，用户不可见，两个回调会原样回传（需自行 urlencode，≤256 字符）
   * @param array|null $device 设备信息，含 start_device_id、end_device_id、materiel_no；
   *                           售货机 / 充电宝 / 充电桩等无人自助设备行业必传
   * @return array|boolean 成功时返回含 order_id（微信支付服务订单号）、state、package 等
   */
  public function order($outOrderNo, $serviceId, $serviceIntroduction, $riskFund, $postPayments = null, $timeRange = null, $postDiscounts = null, $location = null, $notifyUrl = null, $attach = null, $device = null)
  {
    $body = [
      "out_order_no" => $outOrderNo,
      "appid" => $this->merchant->appId(),
      "service_id" => $serviceId,
      "service_introduction" => $serviceIntroduction,
      "risk_fund" => $riskFund,
      "notify_url" => $notifyUrl === null ? $this->merchant->notifyUrl() : $notifyUrl
    ];
    if ($postPayments !== null && $postPayments !== []) {
      $body['post_payments'] = $postPayments;
    }
    if ($timeRange !== null && $timeRange !== []) {
      $body['time_range'] = $timeRange;
    }
    if ($postDiscounts !== null && $postDiscounts !== []) {
      $body['post_discounts'] = $postDiscounts;
    }
    if ($location !== null && $location !== []) {
      $body['location'] = $location;
    }
    if ($attach !== null && $attach !== "") {
      $body['attach'] = $attach;
    }
    if ($device !== null && $device !== []) {
      $body['device'] = $device;
    }

    return $this->request("POST", "/v3/payscore/serviceorder", $body);
  }

  /**
   * 查询支付分订单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012587902
   *
   * $queryId 与 $outOrderNo 二选一：$queryId 是用户拉起支付分确认订单页后微信返回给商户前端的
   * 查单 ID，两者同时传会被微信以参数错误驳回，故传了 $queryId 就只用它。
   *
   * @param string $outOrderNo 商户服务订单号
   * @param string $serviceId 服务 ID
   * @param string|null $queryId 查单 ID（优先于 $outOrderNo）
   * @return array|boolean 成功时返回含 state（CREATED / DOING / DONE / REVOKED / EXPIRED）、
   *                state_description（仅 DOING：USER_CONFIRM / MCH_COMPLETE）、
   *                total_amount、collection（收款信息，含 state、details[].transaction_id 等）等
   */
  public function query($outOrderNo, $serviceId, $queryId = null)
  {
    $query = [
      "appid" => $this->merchant->appId(),
      "service_id" => $serviceId
    ];
    if ($queryId !== null && $queryId !== "") {
      $query['query_id'] = $queryId;
    } else {
      $query['out_order_no'] = $outOrderNo;
    }

    return $this->request("GET", "/v3/payscore/serviceorder", [], $query);
  }

  /**
   * 取消支付分订单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012587905
   *
   * 只能取消尚未进入扣款流程的订单（已创建、用户已确认、待支付），已成功的不能取消；
   * 待支付状态下若用户正在拉起收银台支付或支付分正在扣款，可能报「单据正在扣款中」，
   * 可等 3 分钟后重试。支持原参重入，重试可用同一参数。
   *
   * @param string $outOrderNo 商户服务订单号
   * @param string $reason 撤销原因，用户在微信收到的取消消息中可见，不超过 50 字符
   * @param string|null $serviceId 服务 ID
   * @return array|boolean 成功时返回含 out_order_no、order_id 等
   */
  public function cancel($outOrderNo, $reason, $serviceId = null)
  {
    $body = [
      "appid" => $this->merchant->appId(),
      "reason" => $reason
    ];
    if ($serviceId !== null && $serviceId !== "") {
      $body['service_id'] = $serviceId;
    }

    return $this->request("POST", "/v3/payscore/serviceorder/" . $outOrderNo . "/cancel", $body);
  }

  /**
   * 修改订单金额
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012587957
   *
   * 仅能在收款状态为待支付（collection.state 为 USER_PAYING）时**下调**金额：
   * 先免模式下用户因评估不通过交了押金 / 预付款 / 保证金的订单不支持修改，
   * 已支付的只能走退款。扣款中调用可能报「单据正在扣款中」，可等 3 分钟后重试。
   *
   * @param string $outOrderNo 商户服务订单号
   * @param string $serviceId 服务 ID
   * @param array $postPayments 刷新后的后付费项目，每项含 name、amount（分）、description、count
   * @param integer $totalAmount 刷新后的收款总金额（分），须等于后付费项目金额和减去优惠金额和
   * @param string $reason 修改原因，不超过 50 字符
   * @param array|null $postDiscounts 刷新后的商户优惠，最多 30 条
   * @param array|null $device 设备信息，含 start_device_id、end_device_id、materiel_no
   * @return array|boolean 成功时返回含 state、total_amount、collection 等
   */
  public function modify($outOrderNo, $serviceId, $postPayments, $totalAmount, $reason, $postDiscounts = null, $device = null)
  {
    $body = [
      "appid" => $this->merchant->appId(),
      "service_id" => $serviceId,
      "post_payments" => $postPayments,
      "total_amount" => $totalAmount,
      "reason" => $reason
    ];
    if ($postDiscounts !== null && $postDiscounts !== []) {
      $body['post_discounts'] = $postDiscounts;
    }
    if ($device !== null && $device !== []) {
      $body['device'] = $device;
    }

    return $this->request("POST", "/v3/payscore/serviceorder/" . $outOrderNo . "/modify", $body);
  }

  /**
   * 完结支付分订单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012587955
   *
   * 服务结束后调用，订单转入待扣款（collection.state 为 USER_PAYING），之后微信会按一定频率
   * 自动发起扣款直至成功、期间不会自动失效；也可用 {@see pay()} 主动催收。
   * total_amount 为 0 表示无需扣款。
   *
   * @param string $outOrderNo 商户服务订单号
   * @param string $serviceId 服务 ID
   * @param array $postPayments 最终的后付费项目，最多 100 条
   * @param integer $totalAmount 收款总金额（分），须等于后付费项目金额和减去优惠金额和
   * @param array|null $timeRange 实际服务时间段，含 start_time、end_time 与备注（格式须两端一致）
   * @param array|null $postDiscounts 最终使用的商户优惠，最多 30 条
   * @param array|null $location 实际服务位置，含 end_location
   * @param boolean $profitSharing true 时订单收款资金冻结待分账（详见 {@see WechatPayProfitSharingApi}）
   * @param string|null $goodsTag 订单优惠标记，代金券配置了该标记时须传
   * @param array|null $device 设备信息，含 start_device_id、end_device_id、materiel_no
   * @return array|boolean 成功时返回含 state、total_amount、need_collection 等
   */
  public function complete($outOrderNo, $serviceId, $postPayments, $totalAmount, $timeRange = null, $postDiscounts = null, $location = null, $profitSharing = false, $goodsTag = null, $device = null)
  {
    $body = [
      "appid" => $this->merchant->appId(),
      "service_id" => $serviceId,
      "post_payments" => $postPayments,
      "total_amount" => $totalAmount
    ];
    if ($timeRange !== null && $timeRange !== []) {
      $body['time_range'] = $timeRange;
    }
    if ($postDiscounts !== null && $postDiscounts !== []) {
      $body['post_discounts'] = $postDiscounts;
    }
    if ($location !== null && $location !== []) {
      $body['location'] = $location;
    }
    if ($profitSharing) {
      $body['profit_sharing'] = true;
    }
    if ($goodsTag !== null && $goodsTag !== "") {
      $body['goods_tag'] = $goodsTag;
    }
    if ($device !== null && $device !== []) {
      $body['device'] = $device;
    }

    return $this->request("POST", "/v3/payscore/serviceorder/" . $outOrderNo . "/complete", $body);
  }

  /**
   * 同步订单状态
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012587962
   *
   * 待支付状态下用户改从其他渠道付款后，用它把支付分订单直接置为已完成、避免重复扣款
   * （若支付分随后也扣款成功即重复支付，任选一渠道退款即可）。场景固定为订单收款成功，
   * 故 type 写死 Order_Paid。
   *
   * @param string $outOrderNo 商户服务订单号
   * @param string $serviceId 服务 ID
   * @param string $paidTime 收款成功时间，支持 yyyyMMddHHmmss 与 yyyyMMdd（后者为当天 0 点）；
   *                         须满足「完结订单时间 ＜ paid_time ≤ 调用本接口时间 + 60 秒」
   * @return array|boolean 成功时返回含 state、collection 等
   */
  public function sync($outOrderNo, $serviceId, $paidTime)
  {
    return $this->request("POST", "/v3/payscore/serviceorder/" . $outOrderNo . "/sync", [
      "appid" => $this->merchant->appId(),
      "service_id" => $serviceId,
      "type" => "Order_Paid",
      "detail" => [
        "paid_time" => $paidTime
      ]
    ]);
  }

  /**
   * 发起催收扣款
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4013394596
   *
   * 订单待支付时主动向用户发起收款，不影响支付分自身的自动扣款策略。
   * 若用户正在支付分订单页支付或自动扣款中，可能报「单据正在扣款中」，可等 3 分钟后重试。
   *
   * @param string $outOrderNo 商户服务订单号
   * @param string $serviceId 服务 ID，该服务须具备本接口对应产品的权限
   * @return array|boolean 成功时返回含 out_order_no、order_id 等
   */
  public function pay($outOrderNo, $serviceId)
  {
    return $this->request("POST", "/v3/payscore/serviceorder/" . $outOrderNo . "/pay", [
      "appid" => $this->merchant->appId(),
      "service_id" => $serviceId
    ]);
  }

}
