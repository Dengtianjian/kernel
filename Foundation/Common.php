<?php

use kernel\Foundation\App;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\Output;

/**
 * 导入文件
 *
 * @param string $fileName 文件名称，无需包含.php扩展名，路径相对于当前项目根目录
 * @param array $args 如果导入的文件return了一个函数，就会自动执行，该参数就是执行函数时传入的参数
 * @param string $BasePath 基路径
 * @return false|mixed 返回false意味为导入失败，可能文件不存在，不建议导入的文件return false，可能会在使用Import时误判断
 */
function Import($fileName, $args = [], $BasePath = F_APP_ROOT)
{
  $FileExt = pathinfo($fileName, PATHINFO_EXTENSION);
  if ($FileExt && $FileExt !== "php") {
    throw new Exception(500, 500, "导入文件错误");
  }
  if (!$FileExt) {
    $fileName = "{$fileName}.php";
  }

  $RealFilePath = FileHelper::combinedFilePath($BasePath, $fileName);
  if (!file_exists($RealFilePath)) {
    return false;
  }
  $data = include($RealFilePath);
  if (is_callable($data)) {
    return call_user_func_array($data, $args);
  }
  return $data;
}

/**
 * 格式化debug输出
 * 二次封装Output::debug
 * @deprecated
 * @param mixed ...$data 输出的数据
 * @return void
 */
function formatDebug(...$data)
{
  Output::debug(...$data);
}
/**
 * 获取当前应用实例
 *
 * @return App
 */
function getApp()
{
  return $GLOBALS['App'] ?: $GLOBALS['app'];
}

if (!function_exists("debug")) {
  /**
   * debug输出
   *
   * @param mixed 输出内容
   * @return void
   */
  function debug(...$data)
  {
    Output::debug(...$data);
    exit;
  }
}
