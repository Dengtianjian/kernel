<?php

namespace kernel\Service;

use kernel\Foundation\Service;
use kernel\Model\SettingsModel;

class SettingsService extends Service
{
  /**
   * 设置表模型
   *
   * @var SettingsModel
   */
  protected static $settingsModel = null;
  public static function useService()
  {
    self::$settingsModel = new SettingsModel();
  }
  /**
   * 获取多个设置项
   *
   * @param array ...$names 设置项名称数组
   * @return array 键是设置项名称，值是设置项值
   */
  public static function items(...$names)
  {
    $SettingsData = get_called_class()::$settingsModel->where("name", $names)->getAll();
    $Settings = [];
    foreach ($SettingsData as $item) {
      if ($item['value']) {
        $v = unserialize($item['value']);
        $Settings[$item['name']] = (is_bool($v) && $v === false) && strpos($item['value'], "b:") === false ? $item['value'] : $v;
      } else {
        $Settings[$item['name']] = null;
      }
    }
    return $Settings;
  }
  /**
   * 查询某个设置项是否存在
   *
   * @param string $name 设置项名称
   * @return bool
   */
  public static function exist($name)
  {
    return get_called_class()::$settingsModel->where("name", $name)->exist();
  }
  /**
   * 获取单个设置项值
   *
   * @param string $name 设置项名称
   * @return mixed 设置项值
   */
  public static function item($name)
  {
    $setting = get_called_class()::$settingsModel->where("name", $name)->getOne();
    if (!$setting) return null;
    $v = unserialize($setting['value']);
    return (is_bool($v) && $v === false) && strpos($setting['value'], "b:") === false ? $setting['value'] : $v;
  }
  /**
   * 添加设置项
   *
   * @param string $name 设置项名称
   * @param mixed $value 设置项值
   * @param boolean $serialization 是否需要序列化后存储
   * @return bool
   */
  public static function add($name, $value = null, $serialization = true)
  {
    return get_called_class()::$settingsModel->insert([
      "name" => $name,
      "value" => $serialization ? serialize($value) : $value,
    ]);
  }
  /**
   * 保存单个设置项
   *
   * @param string $name 设置项名称
   * @param string $value 设置项值
   * @param boolean $serialization 是否需要序列化后存储
   * @return bool
   */
  public static function save($name, $value, $serialization = true)
  {
    return get_called_class()::$settingsModel->where("name", $name)->update([
      "value" => $serialization ? serialize($value) : $value,
      "updatedAt" => time()
    ]);
  }
  /**
   * 保存多个设置项值
   *
   * @param array $settings 设置项键值对，键是设置项名称，值是设置项值
   * @return bool
   */
  public static function saveItems($settings)
  {
    foreach ($settings as $name => $value) {
      get_called_class()::$settingsModel->where("name", $name)->update([
        "value" => serialize($value),
        "updatedAt" => time()
      ]);
    }
    return true;
  }
}
