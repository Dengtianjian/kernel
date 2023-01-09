<?php

namespace kernel\Foundation;

use kernel\Foundation\HTTP\Response;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Lang
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
      Response::error(500, "Lang:500001", "Server error");
    }
    Store::setApp([
      "langs" => Lang::all()
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
