<?php

use kernel\Foundation\App;
use kernel\Foundation\Application\Applications;
use kernel\Foundation\Config;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\Output;

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

/**
 * 获取应用实例
 *
 * 应用实例统一由 {@see \kernel\Foundation\Application\Applications} 管理：
 * 首个注册（或显式指定）的应用为**默认应用**，未登记任何 App 时返回 null。
 *
 * 取值规则（两个参数都是应用ID）：
 * - 不传参数：返回默认应用；
 * - 传 `$id` 且已登记：返回该应用；
 * - 传 `$id` 但未登记：**未传第二个参数**时回落到默认应用；
 *   传了 `$fallbackId` 则改取该 ID 对应的应用（该 ID 也未登记时返回 null）。
 *
 * ```php
 * getApp();                    // 默认应用
 * getApp("app");               // id 为 app 的实例；没登记则回落到默认应用
 * getApp("app", "appBackup");  // id 为 app 的实例；没登记则取 appBackup 的实例
 * ```
 *
 * @param string|null $id 应用ID；不传表示取默认应用
 * @param string|null $fallbackId 备用应用ID：`$id` 未登记时改取该应用（不传则回落默认应用）
 * @return App|null 应用实例；无可用实例（未登记且无默认应用）时返回 null
 */
function getApp($id = null, $fallbackId = null)
{
  //* 未指定应用：直接返回默认应用
  if ($id === null) {
    return Applications::default();
  }

  $app = Applications::get($id);
  if ($app !== null) {
    return $app;
  }

  //* 未传备用 ID：回落默认应用；传了则取备用 ID 对应的实例（同样未登记时为 null）
  if (func_num_args() < 2) {
    return Applications::default();
  }

  return Applications::get($fallbackId);
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
   * @param string $name 路径 getter 名：project|kernel|root|data|storage|kernelDir|dir
   * @return string|null
   */
  function path($name = "root")
  {
    $getters = ["project", "kernel", "root", "data", "storage", "kernelDir", "dir"];
    if (!in_array($name, $getters, true)) {
      throw new Error("未知路径名称「{$name}」", 500, 500);
    }
    return Path::{$name}();
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


if (!function_exists("isSafeCallable")) {
  function isSafeCallable($value, array $whitelist = []): bool
  {
    // 字符串类型：必须在白名单中才算安全
    if (is_string($value)) {
      return !empty($whitelist) && in_array($value, $whitelist, true) && is_callable($value);
    }

    // 非字符串（闭包、[$obj, 'method']、可调用对象）：直接判断
    return is_callable($value);
  }
}
