<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\CustomMenu;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 自定义菜单 - 个性化菜单
 *
 * 个性化菜单可根据用户标签、性别、客户端版本等条件为不同用户群展示不同菜单。
 * 个性化菜单的查询可复用 {@see Menu::getMenu()}（响应中的 conditionalmenu 字段）。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Personalized_menu_interface.html
 */
class PersonalizedMenu extends WechatOfficialAccountApi
{
  /**
   * 创建个性化菜单
   *
   * 菜单结构在普通菜单基础上增加 matchrule（匹配规则），如 tag_id、sex、
   * client_platform_type、country、province、city、language 等。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Personalized_menu_interface.html
   *
   * @param array $menuData 含 button 与 matchrule 的菜单数据
   * @return array 微信响应数组，成功时含 menuid
   */
  public function add($menuData)
  {
    return $this->post("cgi-bin/menu/addconditional", $menuData)->getData();
  }
  /**
   * 删除个性化菜单
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Personalized_menu_interface.html
   *
   * @param int|string $menuId 菜单 ID（menuid）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function delete($menuId)
  {
    return $this->post("cgi-bin/menu/delconditional", [
      "menuid" => $menuId
    ])->getData();
  }
  /**
   * 测试个性化菜单匹配结果
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Personalized_menu_interface.html
   *
   * @param string $userId 用户标识，可以是粉丝的 OpenID，也可以是粉丝的微信号
   * @return array 微信响应数组，成功时含 button
   */
  public function tryMatch($userId)
  {
    return $this->post("cgi-bin/menu/trymatch", [
      "user_id" => $userId
    ])->getData();
  }
}
