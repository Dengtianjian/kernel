<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Settings\GetSettingsController;
use kernel\Platform\DiscuzX\Controller\Settings\SaveSettingsController;
use kernel\Platform\DiscuzX\Model\DiscuzXSettingsModel;
use kernel\Service\SettingService;

class DiscuzXSettingService extends SettingService
{
  protected static $SettingsTableName = null;
  public static $Names = [];
  public static $GroupNames = [];
  public static $AdminNames = [];
  /**
   * 使用通用设置存储服务
   * 会存储可以获取的键名、注册settings为前缀路由
   *
   * @param array $names 都可以获取的键名
   * @param array $groupNames 不同用户组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
   * @param array $adminNames 不同管理组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
   * @param string $SettingsTableaName 设置存储表名称，默认是appId+"_settings"
   * @return void
   */
  static function useService(
    $names = [],
    $groupNames = [],
    $adminNames = [],
    $SettingsTableName = F_APP_ID . "_settings"
  ) {
    self::$SettingsTableName = $SettingsTableName;
    self::$Names = $names;
    self::$GroupNames = $groupNames;
    self::$AdminNames = $adminNames;

    Router::get("settings", GetSettingsController::class);
    Router::patch("settings", SaveSettingsController::class);
  }
  static function init()
  {
    return (new DiscuzXSettingsModel(self::$SettingsTableName))->createTable();
  }
  public function __construct()
  {
    $this->settingModel = new DiscuzXSettingsModel(self::$SettingsTableName);
  }
}
