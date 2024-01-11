<?php

namespace kernel\Platform\DiscuzX\Modules\SettingModule;

use kernel\Foundation\Router;
use kernel\Modules\SettingModule\SettingService;
use kernel\Platform\DiscuzX\Controller\Settings as SettingsNamespace;

class DiscuzXSettingService extends SettingService
{
  /**
   * 使用通用设置存储服务
   * 会注册获取设置项、保存设置项的路由
   *
   * @param DiscuzXSettingModuleBase $settingBase 设置功能模块实例
   * @return void
   */
  static function useService(
    $settingBase = NULL
  ) {
    if (is_null($settingBase)) {
      $settingBase = new DiscuzXSettingModuleBase(new DiscuzXSettingsModel());
    }

    Router::get("settings", SettingsNamespace\DiscuzXGetSettingsController::class, [], [
      $settingBase
    ]);
    Router::patch("settings", SettingsNamespace\DiscuzXSaveSettingsController::class, [], [
      $settingBase
    ]);

    parent::useService($settingBase);
  }
  static function init()
  {
    return (new DiscuzXSettingsModel())->createTable();
  }
}
