<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 商家转账
 *
 * 对应微信支付的**新版商家转账**（/v3/fund-app/mch-transfer/*，2024 年起）：
 * 发起转账 → 商户单号/微信单号查单 → 必要时撤销 → 申请电子回单。
 * 注意与「商家转账到零钱」旧版接口（/v3/transfer/batches）不是同一套，旧版未在此实现。
 *
 * 收款模式有两种。微信没有「模式开关」字段，而是两套并列的接口：
 * - **用户确认模式**：{@see transfer()}，收款人须在微信侧确认后才到账；
 * - **用户授权免确认模式**：用户授权一次，之后可免确认直接到账，对应
 *   {@see preTransferWithAuthorization()}（下单，并在用户确认收款时顺带引导授权）、
 *   {@see applyAuthorization()}（单独发起一次授权）、{@see queryAuthorization()}（查授权结果）、
 *   {@see closeAuthorization()}（解除授权）、{@see transferWithAuthorization()}（对已授权用户转账）。
 *
 * 前提：需先在商户平台开通「商家转账」并配置转账场景（transfer_scene_id）、
 * 单笔金额较大的还需传入加密后的收款人姓名；各转账方法还须按场景传入报备信息
 * （transfer_scene_report_infos，见 {@see buildTransferBody()}）。
 *
 * 方法返回约定：成功返回微信响应数据（数组），失败返回 false，错误信息见 getError()；
 * 判断失败请用 `=== false`，详见 {@see WechatPayV3Api::request()}。
 */
class WechatPayTransferApi extends WechatPayV3Api
{
  /**
   * 发起商家转账（用户确认模式）
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012711988
   *
   * 收款人姓名 `user_name` 属敏感信息，微信要求 RSA 加密后传输——本模块尚未集成平台证书，
   * 加密需由调用方自行完成后再传入；金额达到门槛（单笔 2000 元及以上）时微信强制要求该字段。
   *
   * @param string $outBillNo 商户转账单号，商户系统内部唯一
   * @param string $openid 收款用户的 openid（须与商户 appid 同主体）
   * @param integer $transferAmount 转账金额（分）
   * @param string $transferRemark 转账备注，展示给用户
   * @param string $transferSceneId 转账场景 ID，如 1000 现金营销、1001 佣金报酬、1005 公益补助
   * @param string|null $userName 收款人姓名（已加密；单笔大额时微信要求传入）
   * @param string|null $notifyUrl 转账结果回调地址；为 null 时不回调，只能靠查单
   * @param string|null $userRecvPerception 用户收款时感知的收款说明，如「现金奖励」
   * @param array|null $sceneReportInfos 转账场景报备信息，每项含 info_type、info_content（见 {@see buildTransferBody()}）
   * @return array|boolean 成功时返回含 transfer_bill_no（微信转账单号）、state（状态）等
   */
  public function transfer($outBillNo, $openid, $transferAmount, $transferRemark, $transferSceneId, $userName = null, $notifyUrl = null, $userRecvPerception = null, $sceneReportInfos = null)
  {
    $body = $this->buildTransferBody($outBillNo, $transferSceneId, $transferAmount, $transferRemark, $userName, $userRecvPerception, $sceneReportInfos);
    $body['openid'] = $openid;
    if ($notifyUrl !== null && $notifyUrl !== "") {
      $body['notify_url'] = $notifyUrl;
    }
    
    return $this->request("POST", "/v3/fund-app/mch-transfer/transfer-bills", $body);
  }

  /**
   * 发起转账并完成免确认收款授权
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4014399293
   *
   * 免确认模式的推荐入口：在本笔转账的用户确认收款流程中，顺带引导用户完成免确认收款授权，
   * 用户同意后该授权即可长期复用（后续用 {@see transferWithAuthorization()} 直接转账）。
   *
   * 用户未收款成功、未同意授权，或因业务规则/风控限制无法完成授权时，微信不处理授权信息、
   * 也不回调授权结果——此情形下用 {@see queryAuthorization()} 查询会得到 NOT_FOUND。
   *
   * @param string $outBillNo 商户转账单号，商户系统内部唯一
   * @param string $openid 收款用户的 openid（须与商户 appid 同主体）
   * @param integer $transferAmount 转账金额（分）
   * @param string $transferRemark 转账备注，展示给用户
   * @param string $transferSceneId 转账场景 ID，如 1000 现金营销、1001 佣金报酬、1005 公益补助
   * @param array $authorizationInfo 免确认收款授权信息（微信标为可选，本方法即为此而设，故必传），含：
   *                                 user_display_name（用户展示名称，用于区分用户在商户侧的不同账号）、
   *                                 out_authorization_no（商户侧授权单号，一个用户账号只允许一个生效中的授权）、
   *                                 authorization_notify_url（授权结果回调地址，HTTPS、不能携带参数）
   * @param string|null $userName 收款人姓名（已加密；单笔大额时微信要求传入）
   * @param string|null $notifyUrl 转账结果回调地址；为 null 时不回调，只能靠查单
   * @param string|null $userRecvPerception 用户收款时感知的收款说明，如「现金奖励」
   * @param array|null $sceneReportInfos 转账场景报备信息，每项含 info_type、info_content（见 {@see buildTransferBody()}）
   * @return array|boolean 成功时返回含 transfer_bill_no、state、package_info（仅 WAIT_USER_CONFIRM 时返回，
   *                供 APP/JSAPI 调起用户确认收款页）、out_authorization_no 等
   */
  public function preTransferWithAuthorization($outBillNo, $openid, $transferAmount, $transferRemark, $transferSceneId, $authorizationInfo, $userName = null, $notifyUrl = null, $userRecvPerception = null, $sceneReportInfos = null)
  {
    $body = $this->buildTransferBody($outBillNo, $transferSceneId, $transferAmount, $transferRemark, $userName, $userRecvPerception, $sceneReportInfos);
    $body['openid'] = $openid;
    if ($notifyUrl !== null && $notifyUrl !== "") {
      $body['notify_url'] = $notifyUrl;
    }
    $body['authorization_info'] = $authorizationInfo;

    return $this->request("POST", "/v3/fund-app/mch-transfer/transfer-bills/pre-transfer-with-authorization", $body);
  }

  /**
   * 用户授权后转账（免确认收款）
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4014399371
   *
   * 对已取得免确认收款授权的用户直接转账，无需用户再次确认；收款人由授权单号定位，
   * 官方报文**不含 openid**。注意：非 200 响应切勿立刻换商户单号重试，须先查单确认原单
   * 已失败（否则有重复转账的资金风险）。
   *
   * $outAuthorizationNo（商户侧授权单号）与 $authorizationId（微信免确认收款授权单号）
   * 二选一必传——两者在授权成功时微信都会返回、建议都保存，本方法只写入传了值的那个，
   * 都不传会被微信以参数错误驳回（同 {@see WechatPayRefundApi::apply()} 处理
   * out_trade_no / transaction_id 的做法）。
   *
   * @param string $outBillNo 商户转账单号，商户系统内部唯一
   * @param integer $transferAmount 转账金额（分）
   * @param string $transferRemark 转账备注，展示给用户
   * @param string $transferSceneId 转账场景 ID，须与授权时用的场景一致
   * @param string|null $outAuthorizationNo 商户侧授权单号
   * @param string|null $authorizationId 微信免确认收款授权单号
   * @param string|null $userName 收款人姓名（已加密；单笔大额时微信要求传入）
   * @param string|null $userRecvPerception 用户收款时感知的收款说明
   * @param array|null $sceneReportInfos 转账场景报备信息，每项含 info_type、info_content（本接口微信标为必填）
   * @return array|boolean 成功时返回含 state、transfer_bill_no 等；仅 state 为 SUCCESS 才算转账成功，
   *                返回 200 但状态非成功属非预期结果，须联系微信支付客服确认
   */
  public function transferWithAuthorization($outBillNo, $transferAmount, $transferRemark, $transferSceneId, $outAuthorizationNo = null, $authorizationId = null, $userName = null, $userRecvPerception = null, $sceneReportInfos = null)
  {
    $body = $this->buildTransferBody($outBillNo, $transferSceneId, $transferAmount, $transferRemark, $userName, $userRecvPerception, $sceneReportInfos);
    if ($outAuthorizationNo !== null && $outAuthorizationNo !== "") {
      $body['out_authorization_no'] = $outAuthorizationNo;
    }
    if ($authorizationId !== null && $authorizationId !== "") {
      $body['authorization_id'] = $authorizationId;
    }

    return $this->request("POST", "/v3/fund-app/mch-transfer/transfer-bills/transfer", $body);
  }

  /**
   * 发起免确认收款授权
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4015901167
   *
   * 单独申请一次免确认收款授权，返回 package_info 供 JSAPI / APP 调起微信授权页
   * （与「转账时顺带引导授权」的 {@see preTransferWithAuthorization()} 二选一）。
   *
   * 授权申请 24 小时内有效，过期未确认需换 out_authorization_no 重新发起；
   * 同一微信号在同一转账场景下，待确认与已授权的授权单合计最多 5 个；
   * 授权成功后长期有效，直至用户关闭或商户用 {@see closeAuthorization()} 解除。
   *
   * @param string $outAuthorizationNo 商户侧授权单号（商户系统内部唯一，一个用户账号对应唯一一个生效中的授权）
   * @param string $openid 收款用户的 openid（须与商户 appid 同主体）
   * @param string $transferSceneId 转账场景 ID
   * @param string $userDisplayName 用户展示名称（授权详情中的「开通账号」，不支持非 UTF8 字符与表情）
   * @param string $authorizationNotifyUrl 授权结果回调地址，HTTPS、不能携带参数
   * @param string|null $userRecvPerception 用户收款时感知的收款说明
   * @param array|null $sceneInfo 场景信息，含 client_ip、device_id、device_type（IOS / ANDROID / HARMONY / OTHER）
   * @return array|boolean 成功时返回含 state（WAIT_USER_CONFIRM 待用户确认）、package_info、create_time 等
   */
  public function applyAuthorization($outAuthorizationNo, $openid, $transferSceneId, $userDisplayName, $authorizationNotifyUrl, $userRecvPerception = null, $sceneInfo = null)
  {
    $body = [
      "out_authorization_no" => $outAuthorizationNo,
      "appid" => $this->merchant->appId(),
      "openid" => $openid,
      "transfer_scene_id" => $transferSceneId,
      "user_display_name" => $userDisplayName,
      "authorization_notify_url" => $authorizationNotifyUrl
    ];
    if ($userRecvPerception !== null && $userRecvPerception !== "") {
      $body['user_recv_perception'] = $userRecvPerception;
    }
    if ($sceneInfo !== null && $sceneInfo !== []) {
      $body['scene_info'] = $sceneInfo;
    }

    return $this->request("POST", "/v3/fund-app/mch-transfer/user-confirm-authorization", $body);
  }

  /**
   * 商户单号查询授权结果
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4014399423
   *
   * 授权单不存在时微信返回 404 NOT_FOUND（本方法随之返回 false，错误码见 {@see getErrorCode()}）。
   * 这是「未授权成功」的正常表达：经 {@see preTransferWithAuthorization()} 发起的授权，
   * 用户未同意授权时即为该结果；用户超期未确认而关闭的授权单保留 30 天，超期后同样查不到。
   *
   * @param string $outAuthorizationNo 商户侧授权单号
   * @param boolean $displayPackageInfo true 时响应返回 package_info，用于再次调起用户授权信息页（有效期 10 分钟）
   * @return array|boolean 成功时返回含 state（TAKING_EFFECT 生效中 / WAIT_USER_CONFIRM 待确认 / CLOSED 已关闭）、
   *                authorization_id、openid、authorize_time、close_info 等
   */
  public function queryAuthorization($outAuthorizationNo, $displayPackageInfo = false)
  {
    $query = [];
    if ($displayPackageInfo) {
      $query['is_display_authorization'] = true;
    }

    return $this->request("GET", "/v3/fund-app/mch-transfer/user-confirm-authorization/out-authorization-no/" . $outAuthorizationNo, [], $query);
  }

  /**
   * 解除免确认收款授权
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4015653811
   *
   * 商户主动解除（close_reason 为 CLOSE_VIA_MCH_API）后，该授权对应的用户无法再用免确认模式收款；
   * 用户也可自行在微信侧关闭。解除后如需继续免确认转账，须重新申请授权。
   *
   * @param string $outAuthorizationNo 商户侧授权单号
   * @return array|boolean 成功时返回含 state（CLOSED）、close_info（close_time、close_reason）等
   */
  public function closeAuthorization($outAuthorizationNo)
  {
    return $this->request("POST", "/v3/fund-app/mch-transfer/user-confirm-authorization/out-authorization-no/" . $outAuthorizationNo . "/close");
  }

  /**
   * 商户单号查询转账单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012716434
   *
   * @param string $outBillNo 商户转账单号
   * @return array|boolean 成功时返回含 state（ACCEPTED / PROCESSING / WAIT_USER_CONFIRM / TRANSFERING /
   *                SUCCESS / FAIL / CANCELING / CANCELLED）、transfer_bill_no、fail_reason 等
   */
  public function queryByOutBillNo($outBillNo)
  {
    return $this->request("GET", "/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/" . $outBillNo);
  }

  /**
   * 微信单号查询转账单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012716434
   *
   * @param string $transferBillNo 微信转账单号
   * @return array|boolean 成功时返回含 state、out_bill_no 等
   */
  public function queryByTransferBillNo($transferBillNo)
  {
    return $this->request("GET", "/v3/fund-app/mch-transfer/transfer-bills/transfer-bill-no/" . $transferBillNo);
  }

  /**
   * 撤销转账
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012716458
   *
   * 仅部分状态可撤销（如收款人尚未确认收款），已成功的转账不能撤销。
   *
   * @param string $outBillNo 商户转账单号
   * @return array|boolean 成功时返回含 state（通常为 CANCELING / CANCELLED）
   */
  public function cancel($outBillNo)
  {
    return $this->request("POST", "/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/" . $outBillNo . "/cancel");
  }

  /**
   * 申请转账电子回单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012716458
   *
   * 仅对转账成功的单可申请；回单生成后可再调用查询接口取回单地址。
   *
   * @param string $outBillNo 商户转账单号
   * @return array|boolean 成功时返回含 state、create_time 等
   */
  public function elecsign($outBillNo)
  {
    return $this->request("POST", "/v3/fund-app/mch-transfer/elecsign/transfer-bills", [
      "out_bill_no" => $outBillNo
    ]);
  }

  /**
   * 查询转账电子回单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012716458
   *
   * @param string $outBillNo 商户转账单号
   * @return array|boolean 成功时返回含 state（GENERATING / GENERATED / FAILED）、
   *                sign_url / sign_height 等回单信息
   */
  public function queryElecsign($outBillNo)
  {
    return $this->request("GET", "/v3/fund-app/mch-transfer/elecsign/transfer-bills/out-bill-no/" . $outBillNo);
  }

  // ===================================================================
  // 报文组装
  // ===================================================================

  /**
   * 组装转账报文中三种发起方式共有的字段
   *
   * 用户确认模式的 {@see transfer()}、免确认模式的 {@see preTransferWithAuthorization()}
   * 与 {@see transferWithAuthorization()} 报文里以下字段完全一致，差异只在收款人标识与回调：
   * 前两者带 openid（可选 notify_url），后者带 authorization_id 或 out_authorization_no
   * （官方报文不含 openid），因此差异字段仍留在各方法里，只把共有部分收在此处。
   *
   * 只写有值的可选项：微信对部分字段传空串的容忍度不一，与其塞空值不如不传
   * （同 {@see WechatPayProductApi::buildOrderBody()}）。
   *
   * @param string $outBillNo 商户转账单号，商户系统内部唯一
   * @param string $transferSceneId 转账场景 ID，如 1000 现金营销、1001 佣金报酬、1005 公益补助
   * @param integer $transferAmount 转账金额（分）
   * @param string $transferRemark 转账备注，展示给用户
   * @param string|null $userName 收款人姓名（已加密；单笔大额时微信要求传入）
   * @param string|null $userRecvPerception 用户收款时感知的收款说明，如「现金奖励」
   * @param array|null $sceneReportInfos 转账场景报备信息，每项含 info_type（信息类型，15 字符内，场景固定值）
   *                                     与 info_content（信息内容，32 字符内）；同一场景有多项时须填完整
   * @return array 转账报文（不含 openid、notify_url、authorization_info 等差异字段）
   */
  protected function buildTransferBody($outBillNo, $transferSceneId, $transferAmount, $transferRemark, $userName = null, $userRecvPerception = null, $sceneReportInfos = null)
  {
    $body = [
      "appid" => $this->merchant->appId(),
      "out_bill_no" => $outBillNo,
      "transfer_scene_id" => $transferSceneId,
      "transfer_amount" => $transferAmount,
      "transfer_remark" => $transferRemark
    ];
    if ($userName !== null && $userName !== "") {
      $body['user_name'] = $userName;
    }
    if ($userRecvPerception !== null && $userRecvPerception !== "") {
      $body['user_recv_perception'] = $userRecvPerception;
    }
    if ($sceneReportInfos !== null && $sceneReportInfos !== []) {
      $body['transfer_scene_report_infos'] = $sceneReportInfos;
    }

    return $body;
  }
}
