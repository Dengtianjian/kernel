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
    if (strpos($filePath, "gstudio") !== false && \kernel\Foundation\App::mode() === "development") {
      $targetFile = null;
      $targetFileLine = null;
      $backtrace = debug_backtrace();
      foreach ($backtrace as $item) {
        if (array_key_exists("function", $item) && $item['function'] === 'spl_autoload_call') {
          $targetFile = $item['file'];
          $targetFileLine = $item['line'];
          break;
        }
      }
      debug(["autoload 文件不存在", $className, $filePath, [
        "file" => $targetFile,
        "line" => $targetFileLine
      ]]);
    }
  }
}
spl_autoload_register("loader", true, true);
