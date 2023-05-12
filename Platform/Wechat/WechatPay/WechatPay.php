<?php

namespace kernel\Platform\Wechat\WechatPay;

use kernel\Platform\Wechat\Wechat;

class WechatPay extends Wechat
{
  /**
   * 商户ID
   *
   * @var string
   */
  protected $MerchantId = null;
  /**
   * 实例化微信支付类
   *
   * @param string $AppId 公众平台AppId
   * @param string $MerchantId 微信支付平台商户ID
   */
  function __construct($AppId, $MerchantId)
  {
    $this->MerchantId = $MerchantId;

    parent::__construct(null, $AppId);
  }
  /**
   * 生成单号
   *
   * @return string
   */
  protected function gererateTradeNo()
  {
    $now = (string)time();
    $s = str_split($now);
    $nums = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
    $no = [date("YmdHis"), 0];
    for ($i = 0; $i < 1; $i++) {
      for ($i = 0; $i < count($s); $i++) {
        array_push($no, $s[mt_rand(0, 9)]);
      }
    }

    $rand = (string)mt_rand(10000, 99999);
    $before = substr($rand, 0, 2);
    $after = substr($rand, 2);
    array_unshift($no, $before);
    array_push($no, $after);

    return implode("", $no);
  }
  /**
   * 生成32位随机字符串
   *
   * @return string
   */
  protected function gererateNonceString()
  {
    return md5(uniqid() . time());
  }
}
