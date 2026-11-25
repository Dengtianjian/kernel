<?php

namespace kernel\Discuz\Foundation;

use kernel\Modules\WechatPay\WechatPayMerchant;
use kernel\Modules\WechatPay\V3\WechatPayV3;

/**
 * Discuz! X5.0「支付通道（X5.6d）」适配器能力（trait，微信支付 V3）
 *
 * 业务插件在自己的 pay/pay_{通道名}.php 中声明（pay_base 约定：类名 pay_{通道名}）：
 *
 *   class pay_gstudiokernel_wechatv3 extends pay_base
 *   {
 *     use DiscuzPaymentAdapterTrait;
 *
 *     public function __construct()
 *     {
 *       global $_G;
 *       $this->discuzPayChannel = 'gstudio_kernel_wechatv3';
 *       $this->discuzPaySettings = \payment::get($this->discuzPayChannel);
 *       parent::__construct();
 *     }
 *   }
 *
 * 通道配置（channels_add 注册的 param）：merchantId / appId / apiV3Secret /
 * apiV3PrivateKeyFilePath / apiV3PrivateKeySerialNo / notifyUrl / payType(native|jsapi|h5|app) / enable。
 * 7 个 pay_base 方法全部委托基础插件 WechatPayV3（自研签名与 HTTP，无第三方 SDK 依赖）；
 * 私有辅助以 discuz 前缀命名；需要定制时业务类覆盖对应方法即可。
 */
trait DiscuzPaymentAdapterTrait
{
  /** 通道名（payment_channels 的 key，与类名 pay_{通道名} 对应） */
  public $discuzPayChannel = "";
  /** 通道配置（X5 payment::get($channel) 返回） */
  public $discuzPaySettings = [];
  /** 惰性构造的 WechatPayV3 实例 */
  private $discuzPayV3 = null;

  /**
   * 惰性构造 WechatPayV3（商户配置注入）
   *
   * @return WechatPayV3
   */
  private function discuzPayApi()
  {
    if ($this->discuzPayV3 !== null) {
      return $this->discuzPayV3;
    }

    $settings = $this->discuzPaySettings;
    $merchant = new WechatPayMerchant($settings['appId'] ?? "", $settings['merchantId'] ?? "");
    $merchant->apiV3Secret($settings['apiV3Secret'] ?? "");
    $merchant->apiV3PrivateKeyFilePath($settings['apiV3PrivateKeyFilePath'] ?? "");
    $merchant->apiV3PrivateKeySerialNo($settings['apiV3PrivateKeySerialNo'] ?? "");
    $merchant->notifyUrl($settings['notifyUrl'] ?: $this->discuzNotifyUrl());

    return $this->discuzPayV3 = new WechatPayV3($merchant);
  }

  /**
   * 回调地址：通道配置优先；未配置时回退到基础插件的支付回调路由（联调时校准）
   *
   * @return string
   */
  private function discuzNotifyUrl()
  {
    global $_G;
    return rtrim((string) ($_G['siteurl'] ?? ""), '/') . "/plugin.php?id=gstudio_kernel:pay/notify";
  }

  /**
   * 通道是否启用（payment::channels_add 注册时写入 enable=1）
   *
   * @return boolean
   */
  public function enabled()
  {
    return !empty($this->discuzPaySettings['enable']);
  }

  /**
   * 发起支付
   *
   * X5 的 $order 约定：subject（标题）/ description（描述）/ out_biz_no（商户单号）/
   * amount（金额，单位分）/ openid（JSAPI 时必传）。
   * 支付方式由通道配置 payType 决定（native / jsapi / h5 / app，默认 native）。
   * 返回结构在联调时按 X5 支付流程校准（当前透传微信应答）。
   *
   * @param array $order X5 订单数组
   * @return array
   */
  public function pay($order)
  {
    global $_G;

    if (!$this->enabled()) {
      return ["code" => 500, "message" => "Payment channel disabled"];
    }

    $api = $this->discuzPayApi();
    $payType = $this->discuzPaySettings['payType'] ?? "native";
    $outTradeNo = (string) ($order['out_biz_no'] ?? "");
    $total = (int) ($order['amount'] ?? 0);
    $description = trim((string) ($order['subject'] ?? "") . ": " . (string) ($order['description'] ?? ""), " :");

    switch ($payType) {
      case "jsapi":
        $result = $api->jsapi()->order($outTradeNo, (string) ($order['openid'] ?? ""), $total, $description);
        break;
      case "h5":
        $result = $api->h5()->order($outTradeNo, (string) ($_G['clientip'] ?? ""), $total, $description);
        break;
      case "app":
        $result = $api->app()->order($outTradeNo, $total, $description);
        break;
      case "native":
      default:
        $result = $api->native()->order($outTradeNo, $total, $description);
        $payType = "native";
        break;
    }

    return ["code" => 200, "payType" => $payType, "result" => $result];
  }

  /**
   * 订单状态查询（透传微信应答）
   *
   * @param string $out_biz_no 商户单号
   * @return mixed
   */
  public function status($out_biz_no)
  {
    return $this->discuzPayApi()->native()->queryByOutTradeNo((string) $out_biz_no);
  }

  /**
   * 发起退款
   *
   * 委托 {@see WechatPayRefundApi::apply()}：apply($outRefundNo, $refundAmount, $totalAmount,
   * $outTradeNo, $transactionId, $reason, $notifyUrl, ...)。
   *
   * @param string $refund_no 退款单号
   * @param string $trade_no 商户订单号
   * @param integer $total_amount 原订单金额（分）
   * @param integer $refund_amount 退款金额（分）
   * @param string $refund_desc 退款描述
   * @return mixed
   */
  public function refund($refund_no, $trade_no, $total_amount, $refund_amount, $refund_desc)
  {
    return $this->discuzPayApi()->refund()->apply(
      (string) $refund_no,
      (int) $refund_amount,
      (int) $total_amount,
      (string) $trade_no,
      null,
      (string) $refund_desc,
      $this->discuzPaySettings['notifyUrl'] ?: $this->discuzNotifyUrl()
    );
  }

  /**
   * 退款状态查询
   *
   * @param string $refund_no 退款单号
   * @param string $trade_no 商户订单号
   * @return mixed
   */
  public function refund_status($refund_no, $trade_no)
  {
    return $this->discuzPayApi()->refund()->query((string) $refund_no);
  }

  /**
   * 商家转账
   *
   * 委托 {@see WechatPayTransferApi::transfer()}：transfer($outBillNo, $openid, $transferAmount,
   * $transferRemark, $transferSceneId, $userName, ...)。X5 的 $account（收款账号）按微信
   * V3 语义映射为收款人 openid，$realname 映射为 userName；transferSceneId 从通道配置读取。
   * 返回结构在联调时按 X5 转账流程校准。
   *
   * @param string $transfer_no 转账单号
   * @param integer $amount 转账金额（分）
   * @param string $realname 收款人姓名
   * @param string $account 收款账号（openid）
   * @param string $title 转账标题
   * @param string $desc 转账描述
   * @return mixed
   */
  public function transfer($transfer_no, $amount, $realname, $account, $title = '', $desc = '')
  {
    $settings = $this->discuzPaySettings;
    return $this->discuzPayApi()->transfer()->transfer(
      (string) $transfer_no,
      (string) $account,
      (int) $amount,
      (string) ($title ?: $desc),
      (string) ($settings['transferSceneId'] ?? ""),
      $realname !== "" ? $realname : null
    );
  }

  /**
   * 转账状态查询
   *
   * @param string $transfer_no 转账单号
   * @return mixed
   */
  public function transfer_status($transfer_no)
  {
    return $this->discuzPayApi()->transfer()->queryByOutBillNo((string) $transfer_no);
  }
}
