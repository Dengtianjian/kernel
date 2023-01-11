<?php

use gstudio_kernel\Foundation\Iuu;
use gstudio_kernel\Foundation\Output;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

function loader($className)
{
  $className = str_replace("\\", "/", $className);
  $filePath = DISCUZ_ROOT . "/source/plugin/$className.php";
  if (file_exists($filePath)) {
    include_once(DISCUZ_ROOT . "/source/plugin/$className.php");
  }
}
spl_autoload_register("loader", false, true);
