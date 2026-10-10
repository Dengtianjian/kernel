<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\CustomMenu;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 自定义菜单 - 普通菜单
 *
 * 微信公众号自定义菜单相关接口。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Creating_Custom-Defined_Menu.html
 */
class Menu extends WechatOfficialAccountApi
{
  /**
   * 创建自定义菜单
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Creating_Custom-Defined_Menu.html
   *
   * @param array $menuData 菜单结构数据（button 数组）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function createMenu($menuData)
  {
    return $this->post("cgi-bin/menu/create", $menuData)->getData();
  }
  /**
   * 查询自定义菜单
   *
   * 返回普通菜单（menu）与个性化菜单（conditionalmenu）。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Querying_Custom_Menus.html
   *
   * @return array 微信响应数组，成功时含 menu、conditionalmenu
   */
  public function getMenu()
  {
    return $this->get("cgi-bin/menu/get")->getData();
  }
  /**
   * 删除自定义菜单
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Deleting_Custom-Defined_Menu.html
   *
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteMenu()
  {
    return $this->get("cgi-bin/menu/delete")->getData();
  }
  /**
   * 获取自定义菜单配置
   *
   * 用于查询菜单的当前配置（包含未通过接口创建、由后台编辑的菜单）。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Querying_Custom_Menus.html
   *
   * @return array 微信响应数组，成功时含 is_menu_open、selfmenu_info
   */
  public function getCurrentSelfmenuInfo()
  {
    return $this->get("cgi-bin/get_current_selfmenu_info")->getData();
  }
}
