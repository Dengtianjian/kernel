<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 自定义菜单
 *
 * 微信公众号自定义菜单相关接口。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Creating_Custom-Defined_Menu.html
 */
class Menu extends WechatOfficialAccountApi
{
  /**
   * 获取自定义菜单配置
   *
   * @return array 微信响应数组
   */
  public function getCurrentSelfmenuInfo()
  {
    return $this->get("cgi-bin/get_current_selfmenu_info")->getData();
  }
  /**
   * 删除自定义菜单
   *
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteMenu()
  {
    return $this->get("cgi-bin/menu/delete")->getData();
  }
  /**
   * 创建自定义菜单
   *
   * @param array $menuData 菜单结构数据
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function createMenu($menuData)
  {
    return $this->post("cgi-bin/menu/create", $menuData)->getData();
  }
}
