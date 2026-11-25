<?php

if (!defined("DISCUZ_ROOT")) {
  return;
}

/**
 * 加载器
 * @param string $className
 * @return void
 */
function loader($className)
{
  $className = str_replace("\\", "/", $className);
  if (strpos($className, "kernel") !== false && strpos($className, "gstudio_kernel") === false) {
    $className = str_replace("kernel", "gstudio_kernel", $className);
  }
  //* 插件目录位置交给平台函数解析，不自己拼 DISCUZ_ROOT；插件目录不存在时返回空串，走下面的告警分支
  $filePath = DISCUZ_PLUGIN("$className.php");
  if (file_exists($filePath)) {
    include_once($filePath);
  } else {
    if (strpos($filePath, "gstudio") !== false) {
      //* X5 启动早期可能触发本 loader 而 App 尚未装配，mode() 会异常——调试输出仅在装配完成后生效
      try {
        if (\kernel\Foundation\App::mode() === "development") {
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
      } catch (\Throwable $e) {
        //* 应用未装配阶段静默跳过调试输出
      }
    }
  }
}
spl_autoload_register("loader", true, true);
