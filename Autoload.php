<?php

use kernel\Foundation\Log;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

function loader($className)
{
  $className = str_replace("\\", "/", $className);
  $filePath = F_ROOT . "/$className.php";
  if (file_exists($filePath)) {
    include_once(F_ROOT . "/$className.php");
  } else {
    Log::record("Autoload：".$filePath);
  }
}
spl_autoload_register("loader", false, true);
