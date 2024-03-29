<?php

namespace kernel\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Data\Arr;

class Store
{
  /**
   * 设置全局变量
   *
   * @param mixed $value 变量值
   * @return boolean 默认返回true
   */
  static function set($value)
  {
    if (!isset($GLOBALS['_STORE'])) {
      $GLOBALS['_STORE'] = [];
    }
    $store = &$GLOBALS['_STORE'];
    foreach ($value as $key => $valueItem) {
      if (isset($store[$key])) {
        if (\is_array($valueItem)) {
          $store[$key] = Arr::merge($store[$key], $valueItem);
        } else {
          $store[$key] = $valueItem;
        }
      } else {
        $store[$key] = $valueItem;
      }
    }
    return true;
  }
  /**
   * 设置当前app的存储数据
   *
   * @param mixed $value 存储的数据
   * @return bool
   */
  static function setApp($value)
  {
    return self::set([
      "__App" => $value
    ]);
  }
  /**
   * 根据传入的字符串数组路径删除全局变量的值
   *
   * @param string $path 字符串数组路径，/分隔。例如： a/b/c。
   * @return void
   */
  static function remove($path = "")
  {
    if (empty($path)) {
      $GLOBALS['_STORE'] = [];
      return true;
    }
    $paths = explode("/", $path);
    $last = $GLOBALS['_STORE'];
    $lastKey = \array_values($paths);
    $lastKey = $lastKey[count($lastKey) - 1];
    foreach ($paths as $pathItem) {
      if ($lastKey == $pathItem) {
        unset($last[$pathItem]);
        break;
      }
      $last = &$last[$pathItem];
    }
  }
  static function removeApp($path = "")
  {
    $path = empty($path) ? "__App" : "__App/$path";
    return self::remove($path);
  }
  /**
   * 根据传入的数组路径字符串获取全局变量值
   *
   * @param string|null $path 数组路径字符串，用/分隔。
   * @return array|string|integer|boolean 获取到的值
   */
  static function get($path = "")
  {
    if (empty($path)) {
      return $GLOBALS['_STORE'];
    }
    $paths = explode("/", $path);
    $last = $GLOBALS['_STORE'];
    foreach ($paths as $pathItem) {
      if (isset($last[$pathItem])) {
        $last = $last[$pathItem];
      } else {
        $last = null;
        break;
      }
    }
    return $last;
  }
  /**
   * 获取当前运行的app下的数据
   *
   * @param string $path 数组层级路径，用 / 分隔
   * @return mixed
   */
  static function getApp($path = "")
  {
    $path = empty($path) ? "__App" : "__App/$path";
    return self::get($path);
  }
}
