<?php

namespace gstudio_kernel\Platform\Wechat\OfficialAccount;

use gstudio_kernel\Foundation\Data\Arr;

class Menu extends WechatOfficialAccount
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
