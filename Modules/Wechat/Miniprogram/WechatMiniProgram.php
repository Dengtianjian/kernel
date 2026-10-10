<?php

namespace kernel\Platform\Wechat\Miniprogram;

use kernel\Platform\Wechat\WechatAccount;
use kernel\Platform\Wechat\Miniprogram\Apis\Employment;
use kernel\Platform\Wechat\Miniprogram\Apis\ImageAndText\ImageAndText;
use kernel\Platform\Wechat\Miniprogram\Apis\Message\Message;
use kernel\Platform\Wechat\Miniprogram\Apis\Nearby;
use kernel\Platform\Wechat\Miniprogram\Apis\QrCodeAndLink\QrCodeAndLink;
use kernel\Platform\Wechat\Miniprogram\Apis\Search;
use kernel\Platform\Wechat\Miniprogram\Apis\SecretCenter;
use kernel\Platform\Wechat\Miniprogram\Apis\Security\Security;
use kernel\Platform\Wechat\Miniprogram\Apis\StudentIdentity;
use kernel\Platform\Wechat\Miniprogram\Apis\TradeManagement\TradeManagement;
use kernel\Platform\Wechat\Miniprogram\Apis\User;
use kernel\Platform\Wechat\Miniprogram\Apis\UserLogin;
use kernel\Platform\Wechat\Miniprogram\Apis\XPay\XPay;

/**
 * 微信小程序（接口聚合入口）
 *
 * 自身即微信账号（AppId/AppSecret），作为所有小程序接口类的聚合入口：
 * 每个方法返回对应业务领域的接口对象（按需实例化，每次调用返回新实例）。
 *
 * 已聚合的领域：
 * - 用户与登录：user()、userLogin()
 * - 内容安全（旧）：SecretCenter()
 * - 小程序码和小程序链接：qrCodeAndLink()
 * - 消息相关：message()
 * - 用工关系：employment()
 * - 小程序安全：security()
 * - 小程序交易管理服务：tradeManagement()
 * - 微信学生身份快速验证：studentIdentity()
 * - 附近小程序：nearby()
 * - 图像处理与文字识别：imageAndText()
 * - 虚拟支付：xPay()
 * - 微信搜一搜：search()
 *
 * 使用示例：
 *
 * ```php
 * $mp = new WechatMiniProgram($appId, $appSecret);
 * $mp->qrCodeAndLink()->qrCode()->getWxaCodeUnlimit("scene=1");
 * $mp->message()->subscribe()->send($openid, $templateId, $data);
 * $mp->security()->contentSecurity()->msgSecCheck($content, $openid);
 * ```
 */
class WechatMiniProgram extends WechatAccount
{
  /**
   * 用户登录相关接口（JSCode 换登录态、检查/重置登录态）
   *
   * @return UserLogin
   */
  public function userLogin()
  {
    return new UserLogin($this);
  }
  /**
   * 用户信息相关接口（插件 openpid、加密信息、支付后 unionid、encryptKey、手机号）
   *
   * @return User
   */
  public function user()
  {
    return new User($this);
  }
  /**
   * 内容安全（旧类，功能已被 security()->contentSecurity() 覆盖）
   *
   * @see Security
   * @return SecretCenter
   */
  public function SecretCenter()
  {
    return new SecretCenter($this);
  }
  /**
   * 小程序码和小程序链接
   *
   * @return QrCodeAndLink
   */
  public function qrCodeAndLink()
  {
    return new QrCodeAndLink($this);
  }
  /**
   * 消息相关（动态消息、订阅消息）
   *
   * @return Message
   */
  public function message()
  {
    return new Message($this);
  }
  /**
   * 用工关系
   *
   * @return Employment
   */
  public function employment()
  {
    return new Employment($this);
  }
  /**
   * 小程序安全（内容安全、安全风控）
   *
   * @return Security
   */
  public function security()
  {
    return new Security($this);
  }
  /**
   * 小程序交易管理服务（订单发货、品牌）
   *
   * @return TradeManagement
   */
  public function tradeManagement()
  {
    return new TradeManagement($this);
  }
  /**
   * 微信学生身份快速验证
   *
   * @return StudentIdentity
   */
  public function studentIdentity()
  {
    return new StudentIdentity($this);
  }
  /**
   * 附近小程序
   *
   * @return Nearby
   */
  public function nearby()
  {
    return new Nearby($this);
  }
  /**
   * 图像处理与文字识别（图像处理、OCR）
   *
   * @return ImageAndText
   */
  public function imageAndText()
  {
    return new ImageAndText($this);
  }
  /**
   * 虚拟支付
   *
   * @return XPay
   */
  public function xPay()
  {
    return new XPay($this);
  }
  /**
   * 微信搜一搜（数据推送）
   *
   * @return Search
   */
  public function search()
  {
    return new Search($this);
  }
}
