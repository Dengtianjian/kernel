<?php

namespace kernel\Modules\Setting;

use kernel\Foundation\Application\Applications;
use kernel\Foundation\Facade\Facade;
use kernel\Modules\Setting\SettingModule;

class Setting extends Facade
{
  protected static function resolve()
  {
    return Applications::default()->setting();
  }
}
