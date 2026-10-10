<?php

namespace kernel\Modules\WechatPay;

use kernel\Foundation\Data\Str;
use kernel\Foundation\Object\AbilityBaseObject;

class WechatPay extends AbilityBaseObject
{
  /**
   * 商户ID
   *
   * @var string
   */
  protected $merchantId = null;
  /**
   * 实例化微信支付类
   *
   * @param string $AppId 公众平台AppId
   * @param string $MerchantId 微信支付平台商户ID
   */
  function __construct($AppId, $merchantId)
  {
    $this->merchantId = $merchantId;

    parent::__construct(null, $AppId);
  }
  /**
   * 生成单号
   *
   * @return string
   */
  protected function gererateTradeNo()
  {
    return Str::serialNo();
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
