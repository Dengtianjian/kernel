<?php

use kernel\Foundation\Log;
use kernel\Foundation\Response;

define("F_ROOT",  str_replace("\\", "/", realpath(dirname(__DIR__))));
define("F_KERNEL_ROOT", str_replace("\\", "/", __DIR__));
define("F_KERNEL", true);

//* 获取URL地址
$url = "";
if (strtolower($_SERVER['REQUEST_SCHEME']) === 'https' && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] === 'on') {
  $url .= "https://";
} else {
  $url .= "http://";
}
$url .= $_SERVER['HTTP_HOST'] . ":" . $_SERVER['SERVER_PORT'];
define("F_BASE_URL", $url);

include_once("../kernel/Autoload.php");

//* 错误处理：系统错误、编码错误、编译错误、PHP内核错误、编译Warning级别、Warning级别都写入日志
function errorHandler(
  int $errno,
  string $errstr,
  string $errfile,
  int $errline
) {
  $errorLevels = [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_CORE_WARNING, E_COMPILE_WARNING, E_WARNING]; //* 写入日志的错误级别 包含 致命性错误级别
  $deadlyLevels = [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]; //* 致命性错误级别
  if (in_array($errno, $errorLevels)) {
    Log::record([
      "errno" => $errno,
      "errstr" => $errstr,
      "errfile" => $errfile,
      "errline" => $errline
    ]);
  }
  if (in_array($errno, $deadlyLevels)) {
    Response::error(500, "ServerError:500000", "服务器错误", [], [
      "errno" => $errno,
      "errstr" => $errstr,
      "errfile" => $errfile,
      "errline" => $errline
    ]);
  }
  return true;
}

\set_error_handler("errorHandler", \E_ALL);
