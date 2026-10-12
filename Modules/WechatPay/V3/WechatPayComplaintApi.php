<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 消费者投诉（消费者投诉 2.0）
 *
 * 对应微信支付的**消费者投诉 2.0**（/v3/merchant-service/*）：
 * 主动查询（{@see queryComplaints()} 列表 / {@see queryComplaint()} 详情 /
 * {@see queryNegotiationHistory()} 协商历史）→ {@see reply()} 回复用户（首次回复后投诉单由
 * 「待处理」转为「处理中」）→ 与用户协商一致后 {@see complete()} 反馈处理完成。
 * 用户申请退款的投诉单还需用 {@see updateRefundProgress()} 给出审批结果（同意后仍要另行用
 * {@see WechatPayRefundApi::apply()} 发起原路退款）；
 * 详情里 need_immediate_service 为 true 的投诉单走 {@see replyImmediateService()} 的新版消息格式回复。
 *
 * 实时接收投诉：先用 {@see addNotificationUrl()} 配置回调地址，之后投诉产生与状态变更时微信会推送
 * 通知（回调解密用 {@see WechatPayV3Api::decryptNotifyData()}，本类不处理回调）。
 *
 * 回复中要带图片凭证时，先用 {@see uploadImage()} 上传拿到 media_id 再传入（最多 4 张）——
 * 该接口是 multipart 上传，发送与签名由 {@see WechatPayV3Api::requestUpload()} 承担。
 *
 * 详情与列表里的 payer_phone 是**加密字段**，需按微信《如何使用 API 证书解密敏感字段》自行解密。
 *
 * 方法返回约定：成功返回微信响应数据（数组）、失败返回 false，错误信息见 getError()；
 * 回复用户 / 反馈处理完成 / 更新退款审批结果 / 删除回调地址微信返回 HTTP 204 无响应体，
 * 本类统一转成 true（同 {@see WechatPayProductApi::close()}），判断失败请用 `=== false`。
 */
class WechatPayComplaintApi extends WechatPayV3Api
{
  /**
   * 查询投诉单列表
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012533431
   *
   * 按投诉发生日期分页查询，**查询日期跨度不能超过 30 天**；少于 48 小时的投诉单微信可能
   * 尚未生成，建议结合回调通知使用。
   *
   * @param string $beginDate 投诉发生开始日期，格式 yyyy-MM-dd
   * @param string $endDate 投诉发生结束日期，格式 yyyy-MM-dd（与 $beginDate 跨度 ≤ 30 天）
   * @param integer|null $limit 分页大小，范围 [1,50]
   * @param integer|null $offset 分页开始位置，从 0 开始计数
   * @param string|null $complaintedMchid 被诉商户号（服务商 / 渠道商查指定子商户时传）
   * @return array|boolean 成功时返回含 data（投诉单数组，每项含 complaint_id、complaint_state、
   *                complaint_detail、complaint_order_info、need_immediate_service 等）、
   *                limit、offset、total_count（仅 offset 为 0 时返回）
   */
  public function queryComplaints($beginDate, $endDate, $limit = null, $offset = null, $complaintedMchid = null)
  {
    $query = [
      "begin_date" => $beginDate,
      "end_date" => $endDate
    ];
    if ($limit !== null) {
      $query['limit'] = $limit;
    }
    if ($offset !== null) {
      $query['offset'] = $offset;
    }
    if ($complaintedMchid !== null && $complaintedMchid !== "") {
      $query['complainted_mchid'] = $complaintedMchid;
    }

    return $this->request("GET", "/v3/merchant-service/complaints-v2", [], $query);
  }

  /**
   * 查询投诉单详情
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012533436
   *
   * 没收到投诉通知时可主动用它查处理结果；返回的 complaint_state 为
   * PENDING（待处理）/ PROCESSING（处理中）/ PROCESSED（已处理完成）。
   *
   * @param string $complaintId 投诉单号
   * @return array|boolean 成功时返回含 complaint_id、complaint_state、problem_type、apply_refund_amount、
   *                complaint_media_list（用户凭证，media_url 指向图片请求接口）、payer_phone（**加密**）等
   */
  public function queryComplaint($complaintId)
  {
    return $this->request("GET", "/v3/merchant-service/complaints-v2/" . $complaintId);
  }

  /**
   * 查询投诉单协商历史
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012533439
   *
   * 分页查询用户与商户的协商记录，便于按处理历史制定后续方案。建议先看每条的 operate_type
   * （MERCHANT_RESPONSE 商户留言 / USER_RESPONSE 用户留言 / MERCHANT_APPROVE_REFUND 商户同意退款
   * / PLATFORM_RESPONSE 平台留言等）再取对应内容；image_list 官方已标注将废弃，
   * 请用 complaint_media_list。
   *
   * @param string $complaintId 投诉单号
   * @param integer|null $limit 分页大小，范围 [1,300]
   * @param integer|null $offset 分页开始位置，从 0 开始计数
   * @return array|boolean 成功时返回含 data（协商历史数组，每项含 log_id、operator、operate_time、
   *                operate_type、operate_details、complaint_media_list 等）、limit、offset、total_count
   */
  public function queryNegotiationHistory($complaintId, $limit = null, $offset = null)
  {
    $query = [];
    if ($limit !== null) {
      $query['limit'] = $limit;
    }
    if ($offset !== null) {
      $query['offset'] = $offset;
    }

    return $this->request("GET", "/v3/merchant-service/complaints-v2/" . $complaintId . "/negotiation-historys", [], $query);
  }

  /**
   * 回复用户
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012467254
   *
   * 提交客诉处理方案；首次回复后投诉单状态变为「处理中」。
   *
   * @param string $complaintId 投诉单号
   * @param string $complaintedMchid 被诉商户号（投诉单详情里的被诉商户号）
   * @param string $responseContent 回复内容，不超过 500 字符
   * @param array|null $responseImages 回复图片，传 {@see uploadImage()} 返回的 media_id，最多 4 张
   * @param string|null $jumpUrl 引导用户跳转的商户客诉处理页地址（HTTPS；属灰度能力）
   * @param string|null $jumpUrlText 跳转链接文案，展示在回复内容之后；传了 $jumpUrl 就必须传它
   * @param array|null $miniProgramJumpInfo 跳转小程序信息，含 appid、path、text（属灰度能力）
   * @return boolean|array 成功返回 true（微信返回 HTTP 204，无响应体）；失败返回 false
   */
  public function reply($complaintId, $complaintedMchid, $responseContent, $responseImages = null, $jumpUrl = null, $jumpUrlText = null, $miniProgramJumpInfo = null)
  {
    $body = [
      "complainted_mchid" => $complaintedMchid,
      "response_content" => $responseContent
    ];
    if ($responseImages !== null && $responseImages !== []) {
      $body['response_images'] = $responseImages;
    }
    if ($jumpUrl !== null && $jumpUrl !== "") {
      $body['jump_url'] = $jumpUrl;
    }
    if ($jumpUrlText !== null && $jumpUrlText !== "") {
      $body['jump_url_text'] = $jumpUrlText;
    }
    if ($miniProgramJumpInfo !== null && $miniProgramJumpInfo !== []) {
      $body['mini_program_jump_info'] = $miniProgramJumpInfo;
    }

    return $this->noContentAsTrue($this->request("POST", "/v3/merchant-service/complaints-v2/" . $complaintId . "/response", $body));
  }

  /**
   * 反馈处理完成
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012467255
   *
   * 务必在真实处理完用户投诉后调用：调用后微信会向用户下发满意度评价，
   * 未妥善处理会造成重复投诉，重复投诉率是客诉处理质量的考核指标。
   *
   * @param string $complaintId 投诉单号
   * @param string $complaintedMchid 被诉商户号
   * @return boolean|array 成功返回 true（微信返回 HTTP 204，无响应体）；失败返回 false
   */
  public function complete($complaintId, $complaintedMchid)
  {
    return $this->noContentAsTrue($this->request("POST", "/v3/merchant-service/complaints-v2/" . $complaintId . "/complete", [
      "complainted_mchid" => $complaintedMchid
    ]));
  }

  /**
   * 更新退款审批结果
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012467256
   *
   * 针对用户发起的退款申请给出审批结果：同意（APPROVE）后**仍须另行调用退款接口**原路退款，
   * 退款到账后投诉单自动转为「处理完成」；拒绝（REJECT）后投诉单同样自动转为「处理完成」。
   *
   * @param string $complaintId 投诉单号
   * @param string $action 审批动作：APPROVE（同意退款）/ REJECT（拒绝退款）
   * @param integer|null $launchRefundDay 同意的预计发起退款时间，单位为工作日，0 表示当天
   * @param string|null $rejectReason 拒绝原因，不超过 200 字符
   * @param array|null $rejectMediaList 拒绝的举证图片，传 {@see uploadImage()} 返回的 media_id，最多 4 张
   * @param string|null $remark 需要向微信支付客服反馈的备注信息
   * @return boolean|array 成功返回 true（微信返回 HTTP 204，无响应体）；失败返回 false
   */
  public function updateRefundProgress($complaintId, $action, $launchRefundDay = null, $rejectReason = null, $rejectMediaList = null, $remark = null)
  {
    $body = [
      "action" => $action
    ];
    if ($launchRefundDay !== null) {
      $body['launch_refund_day'] = $launchRefundDay;
    }
    if ($rejectReason !== null && $rejectReason !== "") {
      $body['reject_reason'] = $rejectReason;
    }
    if ($rejectMediaList !== null && $rejectMediaList !== []) {
      $body['reject_media_list'] = $rejectMediaList;
    }
    if ($remark !== null && $remark !== "") {
      $body['remark'] = $remark;
    }

    return $this->noContentAsTrue($this->request("POST", "/v3/merchant-service/complaints-v2/" . $complaintId . "/update-refund-progress", $body));
  }

  /**
   * 回复需要即时服务的投诉单
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4017151596
   *
   * 给 need_immediate_service 为 true 的投诉单用新版消息格式回复，支持富文本、图片、链接、
   * FAQ 列表、按钮与按钮组（$message 原样透传，字段以官方文档为准——与
   * {@see WechatPayProductApi::combine()} 透传子单报文的做法一致）。
   *
   * @param string $complaintId 投诉单号
   * @param string $complaintedMchid 被诉商户号
   * @param array $message 消息内容，含 blocks（消息块列表）、sender_identity（MANUAL / MACHINE）、
   *                       custom_data 等；图片块传 {@see uploadImage()} 返回的 media_id
   * @param string $idempotentId 幂等 ID，相同 ID 的请求只处理一次，需商户自行生成并保存
   * @return array|boolean 成功时返回含 log_id（操作流水号，与协商历史里的 log_id 对应）
   */
  public function replyImmediateService($complaintId, $complaintedMchid, $message, $idempotentId)
  {
    return $this->request("POST", "/v3/merchant-service/complaints-v2/" . $complaintId . "/response-immediate-service", [
      "complainted_mchid" => $complaintedMchid,
      "message" => $message,
      "idempotent_id" => $idempotentId
    ]);
  }

  /**
   * 上传图片凭证
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012467250
   *
   * 回复用户（{@see reply()}）、拒绝退款（{@see updateRefundProgress()}）与即时服务回复里的图片，
   * 都要先经本接口上传拿到 media_id 再传单号，每处最多 4 张。
   * 只支持 JPG / BMP / PNG，文件大小不超过 2M。
   *
   * 请求是 multipart/form-data，签名串中的请求报文主体为 meta 的 JSON 串（不是文件内容），
   * 由 {@see WechatPayV3Api::requestUpload()} 统一编码一次供签名与表单共用。
   *
   * @param string $filePath 图片文件的绝对路径
   * @param string|null $fileName 媒体文件名称，后缀须为 JPG / BMP / PNG；为 null 时取路径中的文件名
   * @return array|boolean 成功时返回含 media_id
   */
  public function uploadImage($filePath, $fileName = null)
  {
    return $this->requestUpload("/v3/merchant-service/images/upload", [
      "filename" => $fileName === null ? basename($filePath) : $fileName,
      "sha256" => hash_file("sha256", $filePath)
    ], $filePath);
  }

  /**
   * 创建投诉通知回调地址
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012458679
   *
   * 配置后，用户产生新投诉或投诉状态变更时微信会向该地址推送通知。仅支持 HTTPS。
   *
   * @param string $url 通知地址，仅支持 HTTPS
   * @return array|boolean 成功时返回含 mchid、url
   */
  public function addNotificationUrl($url)
  {
    return $this->request("POST", "/v3/merchant-service/complaint-notifications", [
      "url" => $url
    ]);
  }

  /**
   * 查询投诉通知回调地址
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012459014
   *
   * @return array|boolean 成功时返回含 mchid、url
   */
  public function queryNotificationUrl()
  {
    return $this->request("GET", "/v3/merchant-service/complaint-notifications");
  }

  /**
   * 更新投诉通知回调地址
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012459282
   *
   * 未创建过回调地址时可先用 {@see addNotificationUrl()}；本接口走 PUT。
   *
   * @param string $url 新的通知地址，仅支持 HTTPS
   * @return array|boolean 成功时返回含 mchid、url
   */
  public function updateNotificationUrl($url)
  {
    return $this->request("PUT", "/v3/merchant-service/complaint-notifications", [
      "url" => $url
    ]);
  }

  /**
   * 删除投诉通知回调地址
   * @link https://pay.weixin.qq.com/doc/v3/merchant/4012460452
   *
   * 删除后不再接收投诉通知；需要时可用 {@see addNotificationUrl()} 重新创建。
   *
   * @return boolean|array 成功返回 true（微信返回 HTTP 204，无响应体）；失败返回 false
   */
  public function removeNotificationUrl()
  {
    return $this->noContentAsTrue($this->request("DELETE", "/v3/merchant-service/complaint-notifications"));
  }

  /**
   * 归一「204 无响应体」接口的成功结果
   *
   * 回复用户 / 反馈处理完成 / 更新退款审批结果 / 删除回调地址微信都返回 HTTP 204，
   * {@see WechatPayV3Api::request()} 拿到的数据为 null——直接交给调用方就无法区分
   * 「操作成功」与「没取到数据」，故统一按 {@see WechatPayProductApi::close()} 的做法
   * 转成 true，让失败判定始终是 `=== false` 这一条约定。
   *
   * @param array|null|boolean $response 请求结果
   * @return array|boolean 失败原样返回 false；无响应体（null）时为 true
   */
  protected function noContentAsTrue($response)
  {
    if ($response === false) {
      return false;
    }

    return $response === null ? true : $response;
  }
}
