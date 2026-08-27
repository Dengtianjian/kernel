<?php

namespace kernel\Platform\DiscuzX\Modules\SettingModule;

use kernel\Foundation\Router\Route;
use kernel\Modules\SettingModule\SettingService;
use kernel\Platform\DiscuzX\Controller\Settings as SettingsNamespace;

class DiscuzXSettingService extends SettingService
{
  /**
   * 装配通用设置存储服务
   * 会注册获取设置项、保存设置项的路由
   *
   * @param DiscuzXSettingModuleBase $settingBase 设置功能模块实例
   * @param boolean $RegisterRouter 是否注册路由
   * @return void
   */
  static function bootstrap(
    $settingBase = NULL,
    $RegisterRouter = TRUE
  ) {
    if (is_null($settingBase)) {
      $settingBase = new DiscuzXSettingModuleBase(new DiscuzXSettingsModel());
    }

    if ($RegisterRouter) {
      Route::get("settings", SettingsNamespace\DiscuzXGetSettingsController::class)
        ->parameters([$settingBase]);
      Route::patch("settings", SettingsNamespace\DiscuzXSaveSettingsController::class)
        ->parameters([$settingBase]);
    }

    parent::bootstrap($settingBase);
  }
  static function bootUp()
  {
    return (new DiscuzXSettingsModel())->createTable();
  }
}
