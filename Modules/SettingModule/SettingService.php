<?php

namespace kernel\Modules\SettingModule;

use kernel\Foundation\Service;

/**
 * 设置项服务类
 */
class SettingService extends Service
{
  /**
   * 设置项模块实例
   *
   * @var SettingModuleBase
   */
  protected static $SettingModuleBaseInstance = NULL;
  static function useService(SettingModuleBase $SMB = NULL)
  {
    self::$SettingModuleBaseInstance = get_called_class()::$SettingModuleBaseInstance = $SMB;
  }
  /**
   * 获取多个设置项
   *
   * @param array ...$names 设置项名称数组
   * @return array 键是设置项名称，值是设置项值
   */
  static function items(...$names)
  {
    return self::$SettingModuleBaseInstance->items(...$names);
  }
  /**
   * 获取单个设置项值
   *
   * @param string $name 设置项名称
   * @return mixed 设置项值
   */
  static function item($name)
  {
    return self::$SettingModuleBaseInstance->item($name);
  }
  /**
   * 查询某个设置项是否存在
   *
   * @param string $name 设置项名称
   * @return bool
   */
  static function exist($name)
  {
    return self::$SettingModuleBaseInstance->exist($name);
  }
  /**
   * 添加设置项
   *
   * @param string $name 设置项名称
   * @param mixed $value 设置项值
   * @param boolean $serialization 是否需要序列化后存储
   * @return bool
   */
  static function add($name, $value = null, $serialization = true)
  {
    return self::$SettingModuleBaseInstance->add($name, $value, $serialization);
  }
  /**
   * 保存单个设置项
   *
   * @param string $name 设置项名称
   * @param string $value 设置项值
   * @param boolean $serialization 是否需要序列化后存储
   * @return bool
   */
  static function save($name, $value, $serialization = true)
  {
    return self::$SettingModuleBaseInstance->save($name, $value, $serialization);
  }
  /**
   * 保存多个设置项值
   *
   * @param array $settings 设置项键值对，键是设置项名称，值是设置项值
   * @return bool
   */
  static function saveItems($settings)
  {
    return self::$SettingModuleBaseInstance->saveItems($settings);
  }
}
