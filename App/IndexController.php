<?php

namespace gstudio_kernel\App;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Controller\AuthController;
use gstudio_kernel\Foundation\File;
use gstudio_kernel\Foundation\Iuu;
use gstudio_kernel\Foundation\Lang;

class IndexController extends AuthController
{
  public function data()
  {
    define("IN_ADMINCP", true);
    // include_once libfile("function/plugin");
    $Iuu = new Iuu("gstudio_kernel", "0.4.9");
    $Iuu->upgrade();
    return 1;
  }
}
