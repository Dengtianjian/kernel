<?php

use kernel\Foundation\File;
use kernel\Foundation\Output;

/**
 * 导入文件
 *
 * @param string $fileName 文件名称，无需包含.php扩展名，路径相对于当前项目根目录
 * @param array $args 如果导入的文件return了一个函数，就会自动执行，该参数就是执行函数时传入的参数
 * @return false|mixed 返回false意味为导入失败，可能文件不存在，不建议导入的文件return false，可能会在使用Import时误判断
 */
function Import($fileName, $args = [])
{
  $RealFilePath = File::genPath(F_APP_ROOT, $fileName . ".php");
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
 *
 * @param mixed ...$data 输出的数据
 * @return void
 */
function formatDebug(...$data)
{
  Output::debug(...$data);
}
