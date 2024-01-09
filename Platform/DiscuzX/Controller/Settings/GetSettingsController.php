<?php

namespace kernel\Platform\DiscuzX\Controller\Settings;

use kernel\Foundation\HTTP\Request;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXSettingsService;

class GetSettingsController extends DiscuzXController
{
  public $query = ["name"];
  protected $names = []; //* 都可以获取的键名
  protected $groupNames = []; //* 不同用户组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
  protected $adminNames = []; //* 不同管理组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
  public function __construct($R)
  {
    $this->names = DiscuzXSettingsService::$Names;
    $this->groupNames = DiscuzXSettingsService::$GroupNames;
    $this->adminNames = DiscuzXSettingsService::$AdminNames;
    parent::__construct($R);
  }
  public function data()
  {
    if (!$this->query->has("name")) return [];
    global $_G;
    $GetNames = explode(",", $this->query->get("name"));
    $names = $this->names;

    if (isset($this->groupNames[$_G['groupid']])) {
      $names = array_merge($names, $this->groupNames[$_G['groupid']]);
    }

    if (isset($this->adminNames[$_G['adminid']])) {
      $names = array_merge($names, $this->adminNames[$_G['adminid']]);
    }
    $names = array_unique(array_intersect($GetNames, $names));

    return DiscuzXSettingsService::singleton()->items(...$names);
  }
}
