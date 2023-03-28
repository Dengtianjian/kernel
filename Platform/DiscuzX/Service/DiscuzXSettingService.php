<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Settings\GetSettingsController;
use kernel\Platform\DiscuzX\Controller\Settings\SaveSettingsController;
use kernel\Platform\DiscuzX\Model\DiscuzXSettingsModel;
use kernel\Service\SettingService;

class DiscuzXSettingService extends SettingService
{
  /**
   * 使用通用设置存储服务
   * 会存储可以获取的键名、注册settings为前缀路由
   *
   * @param string $SettingsModel 设置表模型 xxx::class
   * @param array $names 都可以获取的键名
   * @param array $groupNames 不同用户组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
   * @param array $adminNames 不同管理组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
   * @return void
   */
  static function init($SettingsModel, $names = [], $groupNames = [], $adminNames = [])
  {
    self::setUseParams([
      "names" => $names,
      "groupNames" => $groupNames,
      "adminNames" => $adminNames,
      "SettingsModel" => $SettingsModel
    ]);
    self::registerRoute();
  }
  public function __construct()
  {
    $C = DiscuzXSettingService::getUseParams()['SettingsModel'];
    $this->settingModel = new $C();
  }
  protected static function registerRoute()
  {
    Router::get("settings", GetSettingsController::class);
    Router::patch("settings", SaveSettingsController::class);
  }
}
