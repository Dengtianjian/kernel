<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Settings\GetSettingsController;
use kernel\Platform\DiscuzX\Controller\Settings\SaveSettingsController;
use kernel\Platform\DiscuzX\Model\DiscuzXSettingsModel;
use kernel\Service\SettingService;

class DiscuzXSettingService extends SettingService
{
  public function __construct()
  {
    $this->settingModel = new DiscuzXSettingsModel();
  }
  private static $instance = null;
  /**
   * 快速调用，单例模式
   *
   * @return DiscuzXSettingService
   */
  public static function quick()
  {
    if (!self::$instance) {
      self::$instance = new DiscuzXSettingService();
    }
    return self::$instance;
  }
  public static function registerRoute()
  {
    Router::get("settings", GetSettingsController::class);
    Router::patch("settings", SaveSettingsController::class);
  }
}
