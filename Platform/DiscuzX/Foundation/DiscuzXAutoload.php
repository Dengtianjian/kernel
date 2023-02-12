<?php

function loader($className)
{
  $className = str_replace("\\", "/", $className);
  if (strpos($className, "kernel") !== false && strpos($className, "gstudio_kernel") === false) {
    $className = str_replace("kernel", "gstudio_kernel", $className);
  }
  $filePath = DISCUZ_ROOT . "/source/plugin/$className.php";
  if (file_exists($filePath)) {
    include_once($filePath);
  } else {
    if (strpos($filePath, "gstudio") !== false && F_APP_MODE === "development") {
      debug($filePath);
    }
  }
}
spl_autoload_register("loader", true, true);
