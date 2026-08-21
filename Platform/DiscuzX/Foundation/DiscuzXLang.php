<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Error;


class DiscuzXLang
{
  private static $langs = [];
  /**
   * 加载语言包
   *
   * @return void
   */
  public static function load($filePath)
  {
    if (\file_exists($filePath)) {
      include_once($filePath);
    } else {
      throw new Error("编码文件不存在", 500, "DiscuzXLang:500001", $filePath);
    }
    $GLOBALS['_STORE']['__App'] = Arr::merge($GLOBALS['_STORE']['__App'] ?? [], [
      "langs" => self::all()
    ]);
  }
  public static function add($langs, $key = null)
  {
    if (\is_array($langs)) {
      self::$langs = array_merge(self::$langs, $langs);
    } else {
      self::$langs[$key] = $langs;
    }
  }
  public static function change($key, $value)
  {
    self::$langs[$key] = $value;
  }
  private static function getValue($keys)
  {
    //* all || [ kernel,view_template ]
    if (\is_string($keys)) {
      return self::$langs[$keys];
    } else {
      $value = self::$langs;
      foreach ($keys as $key) {
        $value = $value[$key];
      }
      return $value;
    }
  }
  public static function connect()
  {
    $keys = \func_get_args();
    foreach ($keys as &$keyItem) {
      $keyItem = \explode("/", $keyItem);
      $keyItem = self::getValue($keyItem);
    }
    return implode("", $keys);
  }
  public static function value($keys)
  {
    //* all | all,save,...
    $keys = func_get_args();
    foreach ($keys as &$keyItem) {
      $keyItem = self::getValue(\explode("/", $keyItem));
    }
    if (\count($keys) === 1) {
      return $keys[0];
    }
    return $keys;
  }
  public static function all()
  {
    return self::$langs;
  }
}
