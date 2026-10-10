<?php

namespace kernel\Platform\Wechat\OfficialAccount;

use kernel\Platform\Wechat\WechatAccount;
use kernel\Platform\Wechat\OfficialAccount\Apis\AccountManagement;
use kernel\Platform\Wechat\OfficialAccount\Apis\Menu;
use kernel\Platform\Wechat\OfficialAccount\Apis\TemplateMessage;
use kernel\Platform\Wechat\OfficialAccount\Apis\UserManagement;
use kernel\Platform\Wechat\OfficialAccount\Apis\WebApp;

/**
 * 微信公众号（接口聚合入口）
 *
 * 自身即微信账号（AppId/AppSecret），作为所有公众号接口类的聚合入口：
 * 每个方法返回对应业务领域的接口对象（按需实例化，每次调用返回新实例），
 * 与小程序侧的 {@see \kernel\Platform\Wechat\Miniprogram\WechatMiniProgram} 结构对称。
 *
 * 已聚合的领域：
 * - 账号管理（带参二维码等）：accountManagement()
 * - 自定义菜单：menu()
 * - 模板消息：templateMessage()
 * - 用户管理：userManagement()
 * - 网页授权与网页应用：webApp()
 *
 * 使用示例：
 *
 * ```php
 * $oa = new WechatOfficialAccount($appId, $appSecret);
 * $oa->templateMessage()->send($openId, $templateId, $data);
 * $oa->menu()->createMenu($menuData);
 * ```
 *
 * 说明：回复用户消息（文本/图片/图文等）不依赖账号与 access_token，为静态工具类，
 * 直接使用 {@see \kernel\Platform\Wechat\OfficialAccount\ReplyMessage}。
 */
class WechatOfficialAccount extends WechatAccount
{
  /**
   * 平台标识
   *
   * @return string
   */
  public function name()
  {
    return "wechatOfficialAccount";
  }
  /**
   * 账号管理相关接口（生成带参数二维码等）
   *
   * @return AccountManagement
   */
  public function accountManagement()
  {
    return new AccountManagement($this);
  }
  /**
   * 自定义菜单相关接口
   *
   * @return Menu
   */
  public function menu()
  {
    return new Menu($this);
  }
  /**
   * 模板消息相关接口
   *
   * @return TemplateMessage
   */
  public function templateMessage()
  {
    return new TemplateMessage($this);
  }
  /**
   * 用户管理相关接口
   *
   * @return UserManagement
   */
  public function userManagement()
  {
    return new UserManagement($this);
  }
  /**
   * 网页授权与网页应用相关接口
   *
   * @return WebApp
   */
  public function webApp()
  {
    return new WebApp($this);
  }
}
