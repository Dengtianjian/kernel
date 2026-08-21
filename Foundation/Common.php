<?php

use kernel\Foundation\App;
use kernel\Foundation\Config;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\Output;
use kernel\Foundation\URL;

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
  $basePath ??= Path::root();

  $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
  if ($fileExt && $fileExt !== "php") {
    // Exception 构造参数顺序：($message, $statusCode, $errorCode)
    throw new Error("导入文件错误", 500, 500);
  }
  if (!$fileExt) {
    $fileName = "{$fileName}.php";
  }

  $realFilePath = FileHelper::combinedFilePath($basePath, $fileName);
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

/**
 * 获取当前应用实例
 *
 * 实例化 App（new App($id)）时自动注册为当前实例（后实例化者覆盖前者），
 * 本函数返回该当前实例。未实例化任何 App 时返回 null。
 *
 * @return App|null 当前应用实例；尚未实例化任何 App 时返回 null
 */
function getApp()
{
  return App::getInstance();
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

if (!function_exists("data_get")) {
  /**
   * 用点号/斜杠路径从数组或对象取值
   *
   * 委托 Arr::get()，支持 "user.profile.name" 或 "user/profile/name" 路径。
   *
   * @param array|object $array 数据源
   * @param string|int|null $key 键路径
   * @param mixed $default 键不存在时的默认值
   * @return mixed
   */
  function data_get($array, $key, $default = null)
  {
    return Arr::get($array, $key, $default);
  }
}

if (!function_exists("data_has")) {
  /**
   * 判断数组/对象是否存在指定键路径
   *
   * 委托 Arr::has()。
   *
   * @param array|object $array 数据源
   * @param string|int|null $key 键路径
   * @return bool
   */
  function data_has($array, $key)
  {
    return Arr::has($array, $key);
  }
}

if (!function_exists("config")) {
  /**
   * 读取配置
   *
   * 委托 Config::get()，支持点号/斜杠路径，如 "database.mysql.host"。
   *
   * @param string $key 配置键路径
   * @param mixed $default 默认值
   * @return mixed
   */
  function config($key = null, $default = null)
  {
    return Config::get($key, $default);
  }
}

if (!function_exists("path")) {
  /**
   * 获取路径
   *
   * 委托 Path::{$name}()，白名单限定，避免任意方法调用。
   *
   * @param string $name 路径 getter 名：projectRoot|kernelRoot|root|data|storage|kernelDir|dir
   * @return string|null
   */
  function path($name = "root")
  {
    $getters = ["projectRoot", "kernelRoot", "root", "data", "storage", "kernelDir", "dir"];
    if (!in_array($name, $getters, true)) {
      throw new Error("未知路径名称「{$name}」", 500, 500);
    }
    return Path::{$name}();
  }

  /**
   * 拼接基础 URL
   *
   * 空参返回仅 baseUrl；传入子路径追加单斜杠；传入绝对 URL 直接返回。
   *
   * @param string|null $path 子路径
   * @return string
   */
  function url($path = "")
  {
    return URL::url($path);
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

if (!function_exists("now")) {
  /**
   * 当前日期时间
   *
   * @param string $format 日期格式，默认 "Y-m-d H:i:s"
   * @return string
   */
  function now($format = "Y-m-d H:i:s")
  {
    return date($format);
  }
}

if (!function_exists("today")) {
  /**
   * 当前日期
   *
   * @return string
   */
  function today()
  {
    return date("Y-m-d");
  }
}
