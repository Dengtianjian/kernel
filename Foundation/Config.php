<?php

namespace kernel\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Data\Arr;

class Config
{
  private static $configs = [];
  /**
   * 读取应用Config文件
   *
   * @param string $filePath 应用配置文件所在路径
   * @return array|bool
   */
  static function read($filePath = null, $appId = F_APP_ID)
  {
    if (!\file_exists($filePath)) {
      return false;
    }
    $Configs = include_once($filePath);
    if (!isset(self::$configs[$appId])) {
      self::$configs[$appId] = [];
    }
    if (isset($Configs) || is_array($Configs)) {
      self::$configs[$appId] = Arr::merge(self::$configs[$appId], $Configs);
      return self::$configs;
    }

    return false;
  }
  /**
   * 获取配置项
   *
   * @param string $key 配置项数组路径字符串，用 / 分隔
   * @param mixed $defaultValue 缺省值，当没有该配置时会返回该值
   * @param string $appId 读取指定APP的配置。为空即为读取当前APP的配置
   * @return array|string|integer|boolean
   */
  static function get($key = null, $defaultValue = null, $appId = F_APP_ID)
  {
    $configs = [];

    if (!isset(self::$configs[$appId])) {
      self::$configs[$appId] = [];
      if (self::read() === false) {
        return $defaultValue;
      }
    }
    $configs = self::$configs[$appId];
    if (!$key) {
      return $configs;
    }
    $key = \explode(",", $key);
    $values = [];
    foreach ($key as $keyItem) {
      $keyItem = \explode("/", $keyItem);
      $value = $configs;
      $lastKey = $keyItem[0];
      foreach ($keyItem as $kkItem) {
        if (isset($value[$kkItem])) {
          $value = $value[$kkItem];
        } else {
          $lastKey = null;
          break;
        }

        $lastKey = $kkItem;
      }
      if ($lastKey !== null) {
        $values[$lastKey] = $value;
      }
    }

    if (count($key) === 1) {
      return \array_pop($values);
    }
    return $values ? $values : $defaultValue;
  }
  /**
   * 覆盖式设置Config的值
   * 修改后的值只会在当前运行中有效，并不会修改到文件的实际值
   *
   * @param array $value 新值
   * @return void
   */
  static function set($value)
  {
    if (!isset(self::$configs[F_APP_ID])) {
      self::$configs[F_APP_ID] = [];
    }
    self::$configs[F_APP_ID] = Arr::merge(self::$configs[F_APP_ID], $value);
  }
}
