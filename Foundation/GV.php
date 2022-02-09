<?php

namespace kernel\Foundation;

class GV
{
  static function generateArrayTree(array $paths, $lastValue)
  {
    if (count($paths) === 0) {
      return $lastValue;
    }
    $path = $paths[0];
    $arr = [];
    if (is_integer($path)) {
      $path = intval($path);
    }
    $arr[$path] = self::generateArrayTree(array_slice($paths, 1), $lastValue);
    return $arr;
  }
  /**
   * 设置全局变量
   *
   * @param any $value 变量值
   * @param string|array $path 数组的层级keys，可字符串，用/分割，可数组
   * @return boolean 默认返回true
   */
  static function set($value, $path = "")
  {
    if ($path) {
      if (is_string($path)) {
        $path = explode("/", $path);
      }
      $value = self::generateArrayTree($path, $value);
    }
    if (Arr::isAssoc($value)) {
      foreach ($value as $key => $valueItem) {
        if ($GLOBALS[$key]) {
          if (\is_array($valueItem)) {
            $GLOBALS[$key] = Arr::merge($GLOBALS[$key], $valueItem);
          } else {
            $GLOBALS[$key] = $valueItem;
          }
        } else {
          $GLOBALS[$key] = $valueItem;
        }
      }
    } else {
      $GLOBALS = \array_merge($GLOBALS, $value);
    }
    return true;
  }
  /**
   * 根据传入的字符串数组路径删除全局变量的值
   *
   * @param string $path 字符串数组路径，/分隔。例如： a/b/c。
   * @return void
   */
  static function remove($path = "_GG")
  {
    $paths = explode("/", $path);
    $last = &$GLOBALS;
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
  /**
   * 根据传入的数组路径字符串获取全局变量值
   *
   * @param string $path 数组路径字符串，用/分隔。
   * @return array|string|integer|boolean 获取到的值
   */
  static function get($path = "")
  {
    $paths = explode("/", $path);
    $last = $GLOBALS;
    foreach ($paths as $pathItem) {
      $last = $last[$pathItem];
    }
    return $last;
  }
  /**
   * 获取_GG下的属性
   *
   * @param string $path 数组路径字符串
   * @return array|string|integer|boolean
   */
  static function getGG($path = "")
  {
    if ($path) {
      $path = "_GG/$path";
    } else {
      $path = "_GG";
    }
    return self::get($path);
  }
  /**
   * 获取当前运行下的app全局属性
   *
   * @param string $path 斜杠分隔
   * @return array 属性
   */
  static function getApp($path = "")
  {
    $path = $path === "" ? "" : "/$path";
    return self::getGG(self::getGG("id") . $path);
  }
}
