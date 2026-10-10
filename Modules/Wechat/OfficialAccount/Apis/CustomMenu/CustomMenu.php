<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\CustomMenu;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 自定义菜单（聚合类）
 *
 * 聚合微信「自定义菜单」分类下的接口，按子域拆分为多个子类：
 * - 普通菜单：{@see Menu}
 * - 个性化菜单：{@see PersonalizedMenu}
 *
 * 使用示例：
 *
 * ```php
 * $oa->customMenu()->menu()->createMenu($menuData);
 * $oa->customMenu()->personalized()->add($conditionalMenuData);
 * ```
 */
class CustomMenu extends WechatOfficialAccountApi
{
  /**
   * 普通菜单相关接口（创建/查询/删除/查询自定义菜单配置）
   *
   * @return Menu
   */
  public function menu()
  {
    return new Menu($this->account);
  }
  /**
   * 个性化菜单相关接口（创建/删除/匹配测试）
   *
   * @return PersonalizedMenu
   */
  public function personalized()
  {
    return new PersonalizedMenu($this->account);
  }
}
