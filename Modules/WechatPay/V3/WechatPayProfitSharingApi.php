<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 分账
 *
 * 覆盖分账的完整流程：请求分账 → 查分账结果 / 查剩余待分金额 → 解冻剩余资金；
 * 以及分账回退（请求回退、查回退结果）、接收方管理（添加/删除）、分账单申请。
 *
 * 前提：下单时须在报文中带 `settle_info.profit_sharing = true`（见
 * {@see WechatPayProductApi::buildOrderBody()} 的 $profitSharing 参数），否则分账请求会被驳回。
 *
 * 方法返回约定：成功返回微信响应数据（数组），失败返回 false，错误信息见 getError()；
 * 判断失败请用 `=== false`，详见 {@see WechatPayV3Api::request()}。
 */
class WechatPayProfitSharingApi extends WechatPayV3Api
{
  /**
   * 请求分账
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_1.shtml
   *
   * @param string $transactionId 原支付订单的微信支付订单号
   * @param string $outOrderNo 商户系统内部的分账单号，同一分账单号多次请求等同一次
   * @param array $receivers 分账接收方列表，每项由微信约定字段组成：
   *                         type（MERCHANT_ID / PERSONAL_OPENID / PERSONAL_SUB_OPENID）、account、amount（分）、
   *                         description，必要时还有 name（**敏感信息，须按微信要求加密后传入**）
   * @param boolean $unfreezeUnsplit true 时剩余未分账金额解冻回分账方；false 时不解冻，可再次分账
   * @return array|boolean 成功时返回含 order_id（微信分账单号）、status、receivers 等
   */
  public function order($transactionId, $outOrderNo, $receivers, $unfreezeUnsplit = false)
  {
    return $this->request("POST", "/v3/profitsharing/orders", [
      "appid" => $this->merchant->appId(),
      "transaction_id" => $transactionId,
      "out_order_no" => $outOrderNo,
      "receivers" => $receivers,
      "unfreeze_unsplit" => $unfreezeUnsplit
    ]);
  }

  /**
   * 查询分账结果
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_2.shtml
   *
   * @param string $outOrderNo 商户分账单号
   * @param string $transactionId 原支付订单的微信支付订单号（微信要求必传）
   * @return array|boolean 成功时返回含 status、receivers 等；处理中时 status 为 PROCESSING
   */
  public function queryOrder($outOrderNo, $transactionId)
  {
    return $this->request("GET", "/v3/profitsharing/orders/" . $outOrderNo, [], [
      "transaction_id" => $transactionId
    ]);
  }

  /**
   * 查询剩余待分金额
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_6.shtml
   *
   * @param string $transactionId 原支付订单的微信支付订单号
   * @return array|boolean 成功时返回含 unsplit_amount（剩余待分金额，分）
   */
  public function queryAmounts($transactionId)
  {
    return $this->request("GET", "/v3/profitsharing/transactions/" . $transactionId . "/amounts");
  }

  /**
   * 解冻剩余资金
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_5.shtml
   *
   * 将订单剩余未分账金额解冻回分账方商户；同一笔订单只能解冻一次，
   * 若请求分账时已传 `unfreeze_unsplit = true` 则无需再调本接口。
   *
   * @param string $transactionId 原支付订单的微信支付订单号
   * @param string $outOrderNo 商户分账单号（与原分账单号一致）
   * @param string $description 解冻说明
   * @return array|boolean 成功时返回含 order_id、status 等
   */
  public function unfreeze($transactionId, $outOrderNo, $description = "")
  {
    $body = [
      "transaction_id" => $transactionId,
      "out_order_no" => $outOrderNo
    ];
    if ($description !== "") {
      $body['description'] = $description;
    }

    return $this->request("POST", "/v3/profitsharing/orders/unfreeze", $body);
  }

  /**
   * 请求分账回退
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_3.shtml
   *
   * 分账完成后需要退回已分给接收方的资金时使用；回退资金退到分账方商户号，
   * 回退金额不能大于该接收方的分账金额。
   *
   * @param string $outOrderNo 原分账单的商户分账单号
   * @param string $outReturnNo 商户回退单号，商户系统内部唯一
   * @param string $returnMchid 回退方商户号（实际出资退款的商户号）
   * @param integer $amount 回退金额（分）
   * @param string $description 回退原因
   * @return array|boolean 成功时返回含 order_id、out_order_no、out_return_no、return_mchid、amount、result 等
   */
  public function returnOrder($outOrderNo, $outReturnNo, $returnMchid, $amount, $description = "")
  {
    $body = [
      "out_order_no" => $outOrderNo,
      "out_return_no" => $outReturnNo,
      "return_mchid" => $returnMchid,
      "amount" => $amount
    ];
    if ($description !== "") {
      $body['description'] = $description;
    }

    return $this->request("POST", "/v3/profitsharing/return-orders", $body);
  }

  /**
   * 查询分账回退结果
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_4.shtml
   *
   * @param string $outReturnNo 商户回退单号
   * @param string $outOrderNo 原分账单的商户分账单号（微信要求必传）
   * @return array|boolean 成功时返回含 result（SUCCESS / FAIL）、amount 等
   */
  public function queryReturnOrder($outReturnNo, $outOrderNo)
  {
    return $this->request("GET", "/v3/profitsharing/return-orders/" . $outReturnNo, [], [
      "out_order_no" => $outOrderNo
    ]);
  }

  /**
   * 添加分账接收方
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_8.shtml
   *
   * 添加后该接收方才可被分账。注意 `name`（接收方姓名/商户全称）属敏感信息，
   * 微信要求 RSA 加密后传输——本模块尚未集成平台证书，加密需由调用方自行完成后再传入。
   *
   * @param string $type 接收方类型：MERCHANT_ID / PERSONAL_OPENID / PERSONAL_SUB_OPENID
   * @param string $account 接收方账号（商户号或 openid）
   * @param string|null $name 接收方姓名/商户全称（已加密；非必填，视类型而定）
   * @param string|null $relationType 与分账方的关系类型，如 SERVICE_PROVIDER、STORE、STAFF 等
   * @return array|boolean 成功时返回含 type、account 等
   */
  public function addReceiver($type, $account, $name = null, $relationType = null)
  {
    $body = [
      "appid" => $this->merchant->appId(),
      "type" => $type,
      "account" => $account
    ];
    if ($name !== null && $name !== "") {
      $body['name'] = $name;
    }
    if ($relationType !== null && $relationType !== "") {
      $body['relation_type'] = $relationType;
    }

    return $this->request("POST", "/v3/profitsharing/receivers/add", $body);
  }

  /**
   * 删除分账接收方
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_9.shtml
   *
   * 删除后不可再向该接收方分账；已分账成功的资金不受影响。
   *
   * @param string $type 接收方类型：MERCHANT_ID / PERSONAL_OPENID / PERSONAL_SUB_OPENID
   * @param string $account 接收方账号（商户号或 openid）
   * @return array|boolean 成功时返回含 type、account 等
   */
  public function deleteReceiver($type, $account)
  {
    return $this->request("POST", "/v3/profitsharing/receivers/delete", [
      "appid" => $this->merchant->appId(),
      "type" => $type,
      "account" => $account
    ]);
  }

  /**
   * 申请分账账单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter8_1_10.shtml
   *
   * 与交易账单同样是「先申请、再下载」：拿到 download_url 后用
   * {@see WechatPayBillApi::download()} 下载。
   *
   * @param string $billDate 账单日期，格式 yyyy-MM-DD
   * @param string|null $tarType 压缩类型，传 GZIP 时下载地址返回的是压缩包；默认不压缩
   * @return array|boolean 成功时返回含 hash_type、hash_value、download_url
   */
  public function bill($billDate, $tarType = null)
  {
    $query = [
      "bill_date" => $billDate
    ];
    if ($tarType !== null && $tarType !== "") {
      $query['tar_type'] = $tarType;
    }

    return $this->request("GET", "/v3/profitsharing/bills", [], $query);
  }
}
