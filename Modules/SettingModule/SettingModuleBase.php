<?php

namespace kernel\Modules\SettingModule;

class SettingModuleBase
{
  /**
   * 设置项模型实例
   *
   * @var SettingsModel
   */
  protected $SettingModelInstance = null;
  public function __construct(SettingsModel $SettingsModel)
  {
    $this->SettingModelInstance = $SettingsModel;
  }
  /**
   * 获取多个设置项
   *
   * @param array ...$names 设置项名称数组
   * @return array 键是设置项名称，值是设置项值
   */
  public function items(...$names)
  {
    return $this->SettingModelInstance->items(...$names);
  }
  /**
   * 获取单个设置项值
   *
   * @param string $name 设置项名称
   * @return mixed 设置项值
   */
  public function item($name)
  {
    $setting = $this->SettingModelInstance->item($name);
    if (!$setting) return null;
    $v = unserialize($setting['value']);
    if (is_bool($v) && $v === false && strpos($setting['value'], "b:") === false) {
      if (array_key_exists("value", $setting)) {
        return $setting['value'];
      }
      return $setting;
    }
    return $v;
  }
  /**
   * 查询某个设置项是否存在
   *
   * @param string $name 设置项名称
   * @return bool
   */
  public function exist($name)
  {
    return $this->SettingModelInstance->where("name", $name)->exist();
  }
  /**
   * 添加设置项
   *
   * @param string $name 设置项名称
   * @param mixed $value 设置项值
   * @param boolean $serialization 是否需要序列化后存储
   * @return bool
   */
  public function add($name, $value = null, $serialization = true)
  {
    return $this->SettingModelInstance->insert([
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
  public function save($name, $value, $serialization = true)
  {
    return $this->SettingModelInstance->where("name", $name)->update([
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
  public function saveItems($settings)
  {
    foreach ($settings as $name => $value) {
      $this->SettingModelInstance->where("name", $name)->update([
        "value" => serialize($value),
        "updatedAt" => time()
      ]);
    }
    return true;
  }
}
