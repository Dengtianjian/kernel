<?php

namespace kernel\Platform\Wechat\WechatPay;

use kernel\Foundation\Data\Str;
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
    return Str::generateSerialNumber();
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
