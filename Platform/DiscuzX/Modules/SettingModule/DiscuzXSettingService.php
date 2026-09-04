<?php

namespace kernel\Platform\DiscuzX\Modules\SettingModule;

use kernel\Foundation\Router\Route;
use kernel\Foundation\Service;
use kernel\Platform\DiscuzX\Controller\Settings as SettingsNamespace;

class DiscuzXSettingService extends Service
{
  /**
   * 装配 Discuz!X 通用设置存储服务
   * 会注册获取设置项、保存设置项的路由。
   *
   * @param DiscuzXSettingModuleBase|null $settingBase 设置功能模块实例
   * @param boolean $RegisterRouter 是否注册路由
   * @return void
   */
  static function bootstrap($settingBase = NULL, $RegisterRouter = TRUE)
  {
    if (is_null($settingBase)) {
      $settingBase = new DiscuzXSettingModuleBase(new DiscuzXSettingsModel());
    }

    if ($RegisterRouter) {
      Route::get("settings", SettingsNamespace\DiscuzXGetSettingsController::class)
        ->parameters([$settingBase]);
      Route::patch("settings", SettingsNamespace\DiscuzXSaveSettingsController::class)
        ->parameters([$settingBase]);
    }
  }

  /**
   * 启动就绪：创建设置表
   *
   * @return mixed
   */
  static function bootUp()
  {
    return (new DiscuzXSettingsModel())->createTable();
  }
}
