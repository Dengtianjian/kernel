<?php

namespace kernel\Platform\Wechat;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 微信账号
 *
 * 承载单个微信账号（公众号/小程序等）的身份信息，即 AppId 与 AppSecret，
 * 作为各业务对象（公众号、小程序等）获取账号数据的来源。
 *
 * 使用示例：
 *
 * ```php
 * $account = new WechatAccount($appId, $appSecret);
 * $account->appId();        // 读取 AppId
 * $account->appSecret();    // 读取 AppSecret
 * ```
 */
class WechatAccount extends AbilityBaseObject
{
  /**
   * 第三方平台 AppId（公众号/小程序等）
   *
   * @var string|null
   */
  protected $appId = null;
  /**
   * 第三方平台 AppSecret
   *
   * @var string|null
   */
  protected $appSecret = null;
  /**
   * 初始化微信账号
   *
   * @param string|null $appId   第三方平台 AppId（公众号/小程序等）
   * @param string|null $secret  第三方平台 AppSecret
   */
  function __construct($appId, $secret)
  {
    $this->appId = $appId;
    $this->appSecret = $secret;
  }
  /**
   * 读写一体：获取或设置 AppId
   *
   * 依据调用时是否传入参数区分读写：
   * - 传入参数：设置 AppId，并返回当前实例以支持链式调用；
   * - 不传参数：返回当前已设置的 AppId。
   *
   * ```php
   * $account->appId("wx1234567890");  // 设置，返回 $account
   * $account->appId();                // 读取，返回 "wx1234567890"
   * ```
   *
   * @param string|null $data 待设置的 AppId
   * @return static|string|null 设置时返回当前实例；读取时返回 AppId，未设置时为 null
   */
  public function appId($data = null)
  {
    if (func_num_args()) {
      $this->appId = $data;
      return $this;
    }

    return $this->appId;
  }
  /**
   * 读写一体：获取或设置 AppSecret
   *
   * 依据调用时是否传入参数区分读写：
   * - 传入参数：设置 AppSecret，并返回当前实例以支持链式调用；
   * - 不传参数：返回当前已设置的 AppSecret。
   *
   * ```php
   * $account->appSecret("abcdefg");  // 设置，返回 $account
   * $account->appSecret();           // 读取，返回 "abcdefg"
   * ```
   *
   * @param string|null $data 待设置的 AppSecret
   * @return static|string|null 设置时返回当前实例；读取时返回 AppSecret，未设置时为 null
   */
  public function appSecret($data = null)
  {
    if (func_num_args()) {
      $this->appSecret = $data;
      return $this;
    }

    return $this->appSecret;
  }
}
