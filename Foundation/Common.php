<?php

use kernel\Foundation\App;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\HTTP\URL;
use kernel\Support\Output;

/**
 * 导入文件
 *
 * 将指定 PHP 文件以 include 方式载入并返回其返回值。
 * 若该文件 return 了一个闭包（\Closure），则自动以 $args 为参数调用它并返回结果。
 *
 * @param string $fileName 文件名称；可带或不带 .php 扩展名，路径相对于 $basePath
 * @param array $args 当导入文件 return 闭包时，传给闭包的参数数组
 * @param string|null $basePath 基路径，默认 Path::root()
 * @return false|mixed 返回 false 表示导入失败（多为文件不存在）；其余为文件 return 的内容
 */
function import($fileName, $args = [], $basePath = null)
{
  $basePath = $basePath ?? Path::root();

  $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
  if ($fileExt && $fileExt !== "php") {
    // Exception 构造参数顺序：($message, $statusCode, $errorCode)
    throw new Error("导入文件错误", 500, 500);
  }
  if (!$fileExt) {
    $fileName = "{$fileName}.php";
  }

  $realFilePath = Path::join($basePath, $fileName);
  if (!file_exists($realFilePath)) {
    return false;
  }
  $data = include($realFilePath);
  // 仅当文件返回真正的闭包时才执行；用 instanceof \Closure 而非 is_callable，
  // 避免可调用数组（如 ['Class','method']）被误当成函数执行
  if ($data instanceof \Closure) {
    return call_user_func_array($data, $args);
  }
  return $data;
}

if (!function_exists("debug")) {
  /**
   * debug输出并终止脚本
   *
   * 委托 Output::debug()：空参时仅输出提示不退出，有数据时输出并 exit。
   *
   * @param mixed ...$data 输出内容
   * @return void
   */
  function debug(...$data)
  {
    Output::debug(...$data);
  }
}

if (!function_exists("dd")) {
  /**
   * dump and die：输出数据并终止脚本
   *
   * 与 debug() 同语义，更短的调试别名。
   *
   * @param mixed ...$data 输出内容
   * @return void
   */
  function dd(...$data)
  {
    Output::debug(...$data);
  }
}

if (!function_exists("dump")) {
  /**
   * 仅输出数据，不终止脚本
   *
   * 相对 dd()/debug()，本函数只打印不退出，适合在循环中调试。
   *
   * @param mixed ...$data 输出内容
   * @return void
   */
  function dump(...$data)
  {
    Output::format(...$data);
  }
}

if (!function_exists("abort")) {
  /**
   * 抛出框架异常
   *
   * 透传 Exception 构造参数（消息在前）。
   *
   * @param string $message 错误信息
   * @param integer $statusCode HTTP状态码
   * @param integer|string $errorCode 错误码
   * @param mixed $errorDetails 错误详情
   * @return void 始终抛出，不会返回
   * @throws Exception
   */
  function abort($message = "Server error", $statusCode = 500, $errorCode = 500, $errorDetails = null)
  {
    throw new Error($message, $statusCode, $errorCode, $errorDetails);
  }
}

if (!function_exists("env")) {
  /**
   * 读取环境变量
   *
   * @param string $key 环境变量名
   * @param mixed $default 未定义时的默认值
   * @return mixed
   */
  function env($key, $default = null)
  {
    $value = getenv($key);
    return $value === false ? $default : $value;
  }
}
