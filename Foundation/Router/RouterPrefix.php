<?php

namespace gstudio_kernel\Foundation\Router;

use gstudio_kernel\Foundation\Router;

class RouterPrefix
{
  protected static $Prefix = "";

  public static function prefix($prefix)
  {
    self::$Prefix = $prefix;
    return Router::class;
  }
}
