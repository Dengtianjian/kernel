<?php

namespace kernel\Platform\Wechat\OfficialAccount;

use kernel\Platform\Wechat\WechatAccount;
use kernel\Platform\Wechat\OfficialAccount\Apis\AccountManagement;
use kernel\Platform\Wechat\OfficialAccount\Apis\Basic;
use kernel\Platform\Wechat\OfficialAccount\Apis\Comment;
use kernel\Platform\Wechat\OfficialAccount\Apis\CustomMenu\CustomMenu;
use kernel\Platform\Wechat\OfficialAccount\Apis\CustomMenu\Menu;
use kernel\Platform\Wechat\OfficialAccount\Apis\Draft;
use kernel\Platform\Wechat\OfficialAccount\Apis\Intelligent\Intelligent;
use kernel\Platform\Wechat\OfficialAccount\Apis\Material\Material;
use kernel\Platform\Wechat\OfficialAccount\Apis\Message\Message;
use kernel\Platform\Wechat\OfficialAccount\Apis\Message\TemplateMessage;
use kernel\Platform\Wechat\OfficialAccount\Apis\Publish;
use kernel\Platform\Wechat\OfficialAccount\Apis\UserManagement\UserManagement;
use kernel\Platform\Wechat\OfficialAccount\Apis\WebApp;

/**
 * 微信公众号（接口聚合入口）
 *
 * 自身即微信账号（AppId/AppSecret），作为所有公众号接口类的聚合入口：
 * 每个方法返回对应业务领域的接口对象（按需实例化，每次调用返回新实例），
 * 与小程序侧的 {@see \kernel\Platform\Wechat\Miniprogram\WechatMiniProgram} 结构对称。
 *
 * 已聚合的领域（对应微信官方文档分类）：
 * - 基础接口：basic()
 * - 自定义菜单：customMenu()（menu()、personalized()）
 * - 基础消息：message()（custom()、mass()、template()、subscribe()）
 * - 素材管理：material()（temporary()、permanent()）
 * - 草稿管理：draft()
 * - 留言管理：comment()
 * - 发布能力：publish()
 * - 用户管理：userManagement()（user()、tag()、blacklist()）
 * - 网页开发：webApp()
 * - 智能接口：intelligent()（semantic()、voice()）
 * - 账号管理（带参二维码等）：accountManagement()
 *
 * 使用示例：
 *
 * ```php
 * $oa = new WechatOfficialAccount($appId, $appSecret);
 * $oa->message()->template()->send($openId, $templateId, $data);
 * $oa->customMenu()->menu()->createMenu($menuData);
 * $oa->material()->permanent()->addNews($articles);
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
   * 基础接口（服务器 IP、jsapi_ticket、网络检测、quota/rid 查询等）
   *
   * @return Basic
   */
  public function basic()
  {
    return new Basic($this);
  }
  /**
   * 自定义菜单（普通菜单、个性化菜单）
   *
   * @return CustomMenu
   */
  public function customMenu()
  {
    return new CustomMenu($this);
  }
  /**
   * 自定义菜单 - 普通菜单（customMenu()->menu() 的快捷方式）
   *
   * @return Menu
   */
  public function menu()
  {
    return new Menu($this);
  }
  /**
   * 基础消息（客服消息、群发消息、模板消息、一次性订阅消息）
   *
   * @return Message
   */
  public function message()
  {
    return new Message($this);
  }
  /**
   * 模板消息（message()->template() 的快捷方式）
   *
   * @return TemplateMessage
   */
  public function templateMessage()
  {
    return new TemplateMessage($this);
  }
  /**
   * 素材管理（临时素材、永久素材）
   *
   * @return Material
   */
  public function material()
  {
    return new Material($this);
  }
  /**
   * 草稿管理
   *
   * @return Draft
   */
  public function draft()
  {
    return new Draft($this);
  }
  /**
   * 留言管理
   *
   * @return Comment
   */
  public function comment()
  {
    return new Comment($this);
  }
  /**
   * 发布能力
   *
   * @return Publish
   */
  public function publish()
  {
    return new Publish($this);
  }
  /**
   * 用户管理（用户信息、用户标签、黑名单）
   *
   * @return UserManagement
   */
  public function userManagement()
  {
    return new UserManagement($this);
  }
  /**
   * 网页开发（网页授权与网页应用）
   *
   * @return WebApp
   */
  public function webApp()
  {
    return new WebApp($this);
  }
  /**
   * 智能接口（语义理解、智能语音）
   *
   * @return Intelligent
   */
  public function intelligent()
  {
    return new Intelligent($this);
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
}
