<?php

namespace kernel\Platform\DiscuzX\Service;

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
}
