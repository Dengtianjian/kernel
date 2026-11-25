<?php

namespace kernel\Discuz\WechatPay\Traits;

use kernel\Discuz\WechatPay\DiscuzWechatPayChannel;
use kernel\Discuz\WechatPay\DiscuzWechatPayMerchant;

/**
 * Discuz! X 支付产品的能力路由（trait，供各产品子类共用）
 *
 * 职责只有三件：
 * - **前置判断**：参数或设备形态超出 Discuz! 的表达能力时，压根不去尝试；
 * - **执行**：把 kernel 的下单参数转成 `pay_wechat` 认得的 `$order` 数组并调用；
 * - **结果判定**：区分「成功」「明确失败（可安全回退）」「结果未知（禁止重下单）」。
 *
 * ## 为什么必须前置判断、不能事后回退
 *
 * Discuz! 的 `pay()` 一旦调用成功，微信侧的订单就已经创建了。如果事后再用同一个
 * `out_trade_no` 走 kernel 重下一次，微信会以「订单已存在/已支付」拒绝，回退反而制造故障。
 * 因此凡是 Discuz! 表达不了的参数（订单有效期、附加数据、分账标识、发票标识、非 CNY），
 * 一律在调用前就跳过本通道。
 *
 * ## 回退安全性
 *
 * `pay()` 返回 `code != 200` 时，说明微信明确拒绝了这次下单（参数错误、AppID 未绑定等），
 * 微信侧**没有**创建订单，此时回退 kernel 是安全的；而返回结构缺失（网络中断、空响应）时
 * 微信侧可能已经建单，这种「结果未知」必须中止，由上层先查单再决定。
 */
trait DiscuzWechatPayProductTrait
{
  /**
   * 取 Discuz! 支付通道
   *
   * 只有商户是 {@see DiscuzWechatPayMerchant} 时才有通道；独立部署误用本 trait 时返回 null，
   * 各产品方法据此直接走父类直连。
   *
   * @return DiscuzWechatPayChannel|null
   */
  protected function discuzChannel()
  {
    if (!($this->merchant instanceof DiscuzWechatPayMerchant)) {
      return null;
    }

    return $this->merchant->channel();
  }

  /**
   * 下单参数是否处于 Discuz! 能完整表达的范围内
   *
   * Discuz! 的下单报文只带 appid / mchid / description / out_trade_no / notify_url / amount，
   * 以下参数一旦传了非默认值，这次下单就不能交给它——否则参数会被静默丢弃，语义被悄悄改变。
   *
   * @param integer|null $periodSeconds 订单有效期（秒）
   * @param string|null $attach 附加数据
   * @param string|null $goodsTag 订单优惠标记
   * @param boolean $profitSharing 是否分账订单
   * @param boolean $supportFapiao 是否开放电子发票入口
   * @param string|null $currency 货币类型
   * @return boolean 全部处于默认值时为 true
   */
  protected function discuzAcceptableOrder($periodSeconds, $attach, $goodsTag, $profitSharing, $supportFapiao, $currency)
  {
    return $periodSeconds === null
      && ($attach === null || $attach === "")
      && ($goodsTag === null || $goodsTag === "")
      && !$profitSharing
      && !$supportFapiao
      && ($currency === null || $currency === "CNY");
  }

  /**
   * 当前请求是否会被 Discuz! 判为移动端（从而下单走 H5 而不是 Native）
   *
   * 判断逻辑与 `pay_wechat::wechat_device()` **完全对齐**：微信内 → 移动端分支；
   * 否则看 `checkmobile()`。本方法必须在调用 Discuz! 之前问，原因是
   * {@see DiscuzWechatPayChannel} 不提供「指定产品」的入口，产品只能由 UA 决定——
   * 事后再发现产品不符，订单已经建出来了。
   *
   * @return boolean 移动端（含微信内）为 true
   */
  protected function discuzIsMobile()
  {
    $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
    if ($userAgent === "") {
      //* 无 UA 时 Discuz! 的 checkmobile() 同样判为非移动端，这里保持一致的结论
      return false;
    }
    if (class_exists("weixin") && \weixin::in_event()) {
      return true;
    }

    return function_exists("checkmobile") ? (bool) \checkmobile() : false;
  }

  /**
   * 构造 Discuz! 下单所需的 `$order` 数组
   *
   * Discuz! 的 V3 下单把 `subject` 与 `description` 拼成 `subject: description`，
   * 这里把 kernel 的单段描述放进 `subject`、`description` 留空，避免同一句话出现两次。
   *
   * @param string $outTradeNo 商户订单号
   * @param integer $total 金额（分）
   * @param string $description 商品描述
   * @return array
   */
  protected function discuzOrderArray($outTradeNo, $total, $description)
  {
    return [
      "out_biz_no" => (string) $outTradeNo,
      "subject" => ($description !== null && $description !== "") ? $description : "商品",
      "description" => "",
      "amount" => (int) $total,
    ];
  }

  /**
   * 通过 Discuz! 发起 Native / H5 下单
   *
   * @param string $outTradeNo 商户订单号
   * @param integer $total 金额（分）
   * @param string $description 商品描述
   * @return string|boolean|null 成功返回支付链接；`null` 表示微信明确拒绝（可安全回退父类）；
   *   `false` 表示结果未知（可能已建单，禁止重下单），错误状态已置上
   */
  protected function discuzPayUrl($outTradeNo, $total, $description)
  {
    $channel = $this->discuzChannel();
    if ($channel === null) {
      return null;
    }

    $result = $channel->pay($this->discuzOrderArray($outTradeNo, $total, $description));

    return $this->discuzResolveUrl($result);
  }

  /**
   * 通过 Discuz! 发起 JSAPI 下单
   *
   * @param string $outTradeNo 商户订单号
   * @param integer $total 金额（分）
   * @param string $description 商品描述
   * @param string $openId 支付者 openid
   * @return string|boolean|null prepay_id；`null` 明确失败可回退；`false` 结果未知
   */
  protected function discuzJsapiPrepayId($outTradeNo, $total, $description, $openId)
  {
    $channel = $this->discuzChannel();
    if ($channel === null) {
      return null;
    }

    $result = $channel->pay_jsapi($this->discuzOrderArray($outTradeNo, $total, $description), $openId);

    return $this->discuzResolveUrl($result);
  }

  /**
   * 解析 Discuz! 的返回，区分三种结局
   *
   * @param mixed $result `pay_wechat` 的返回：`['code' => 200, 'url' => ...]` 或 `['code' => ..., 'message' => ...]`
   * @return string|boolean|null 同 {@see discuzPayUrl()}
   */
  protected function discuzResolveUrl($result)
  {
    if (!is_array($result) || !array_key_exists("code", $result)) {
      //* 结构缺失：多半是 filesock 拿不到响应，微信侧可能已建单，不能重下单
      return $this->break(500, 500, "Discuz! 支付未返回有效结果，请先查单确认后再重试", $result);
    }

    if ($result["code"] == 200 && !empty($result["url"])) {
      return (string) $result["url"];
    }

    if ($result["code"] === null || $result["code"] === "" || $result["code"] === 0) {
      //* 无错误码的失败：同样是结果未知
      return $this->break(500, 500, "Discuz! 支付结果未知，请先查单确认后再重试", $result);
    }

    //* 微信明确拒绝（参数错误、AppID 未绑定等）：微信侧未建单，交给调用方回退
    return null;
  }
}
