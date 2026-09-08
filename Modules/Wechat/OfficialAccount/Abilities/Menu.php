<?php

namespace kernel\Platform\Wechat\OfficialAccount\Abilities;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountAbility;

class Menu extends WechatOfficialAccountAbility
{
  public function getCurrentSelfmenuInfo()
  {
    return $this->get("cgi-bin/get_current_selfmenu_info")->getData();
  }
  public function deleteMenu()
  {
    return $this->get("cgi-bin/menu/delete")->getData();
  }
  public function createMenu($menuData)
  {
    return $this->post("cgi-bin/menu/create", $menuData)->getData();
  }
}
