<?php

namespace kernel\Platform\DiscuzX\Controller\Settings;

use kernel\Foundation\HTTP\Request;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXSettingService;

class SaveSettingsController extends DiscuzXController
{
  public $Admin = true;
  public function data(Request $R)
  {
    $settings = $R->body->some();
    return DiscuzXSettingService::quick()->saveItems($settings);
  }
}
