<?php

namespace kernel\Platform\DiscuzX\Controller\Settings;

use kernel\Foundation\HTTP\Request;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXSettingService;

class GetSettingsController extends DiscuzXController
{
  public $query = ["name"];
  protected $names = []; //* 都可以获取的键名
  protected $groupNames = []; //* 不同用户组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
  protected $adminNames = []; //* 不同管理组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
  public function data(Request $R)
  {
    if (!$this->query->has("name")) return [];
    global $_G;
    $name = $this->query->get("name");
    $SourceNames = explode(",", $name);
    $names = array_intersect($SourceNames, $this->names);

    $groupNames = [];
    if (isset($this->groupNames[$_G['groupid']])) {
      $groupNames = array_intersect($SourceNames, $this->groupNames[$_G['groupid']]);
    }
    $names = array_merge($names, $groupNames);

    $adminNames = [];
    if (isset($this->adminNames[$_G['adminid']])) {
      $adminNames = array_intersect($SourceNames, $this->adminNames[$_G['adminid']]);
    }
    $names = array_merge($names, $adminNames);

    return DiscuzXSettingService::quick()->items(...array_unique($names));
  }
}
