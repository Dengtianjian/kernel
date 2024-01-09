<?php

namespace kernel\Platform\DiscuzX\Controller\Settings;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXSettingsService;

class SaveSettingsController extends DiscuzXController
{
  public $Admin = true;
  public function data()
  {
    $settings = $this->request->body->some();
    return DiscuzXSettingsService::singleton()->saveItems($settings);
  }
}
