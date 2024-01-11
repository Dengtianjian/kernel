<?php

namespace kernel\Platform\DiscuzX\Controller\Settings;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Modules\SettingModule\DiscuzXSettingModuleBase;

class DiscuzXGetSettingsController extends DiscuzXController
{
  public $query = [
    "name" => "string/"
  ];
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
    if (!$this->query->has("name")) return [];

    $names = (array)$this->query->get("name");

    return $this->setting->items(...$names);
  }
}
