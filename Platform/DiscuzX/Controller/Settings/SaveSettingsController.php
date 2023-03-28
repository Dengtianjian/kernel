<?php

namespace kernel\Platform\DiscuzX\Controller\Settings;

use kernel\Foundation\HTTP\Request;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXSettingService;

class SaveSettingsController extends DiscuzXController
{
  public $Admin = true;
  public function data()
  {
    $settings = $this->request->body->some();
    return DiscuzXSettingService::singleton()->saveItems($settings);
  }
}
