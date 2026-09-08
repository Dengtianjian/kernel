<?php

namespace kernel\Modules\DiscuzX\Controller\Settings;

use kernel\Modules\DiscuzX\Foundation\DiscuzXController;
use kernel\Modules\DiscuzX\Modules\SettingModule\DiscuzXSettingModuleBase;

class DiscuzXSaveSettingsController extends DiscuzXController
{
  public $admin = true;
  /**
   * 设置模块实例
   *
   * @var DiscuzXSettingModuleBase
   */
  protected $setting = NULL;
  public function __construct($R, $setting)
  {
    $this->setting = $setting;
    parent::__construct($R);
  }
  public function data()
  {
    $settings = $this->request->body->some();
    return $this->setting->saveItems($settings);
  }
}
