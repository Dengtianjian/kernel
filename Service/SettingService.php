<?php

namespace kernel\Service;

use kernel\Foundation\Router;
use kernel\Foundation\Service;
use kernel\Model\SettingsModel;

class SettingService extends Service
{
  /**
   * 设置表模型
   *
   * @var SettingsModel
   */
  protected $settingModel = null;
  public function __construct()
  {
    $this->settingModel = new SettingsModel();
  }
  /**
   * 获取多个设置项
   *
   * @param array ...$names 设置项名称数组
   * @return array 键是设置项名称，值是设置项值
   */
  public function items(...$names)
  {
    $SettingsData = $this->settingModel->where("name", $names)->getAll();
    $Settings = [];
    foreach ($SettingsData as $item) {
      if ($item['value']) {
        $v = unserialize($item['value']);
        $Settings[$item['name']] = is_null($v) || (is_bool($v) && $v === false) ? $item['value'] : $v;
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
  public function exist($name)
  {
    return $this->settingModel->where("name", $name)->exist();
  }
  /**
   * 获取单个设置项值
   * @deprecated
   *
   * @param string $name 设置项名称
   * @return mixed 设置项值
   */
  public function get($name)
  {
    $setting = $this->settingModel->where("name", $name)->getOne();
    if (!$setting) return null;
    $v = unserialize($setting['value']);
    return is_null($v) || (is_bool($v) && $v === false) ? $setting['value'] : $v;
  }
  /**
   * 获取单个设置项值
   *
   * @param string $name 设置项名称
   * @return mixed 设置项值
   */
  public function item($name)
  {
    $setting = $this->settingModel->where("name", $name)->getOne();
    if (!$setting) return null;
    $v = unserialize($setting['value']);
    return is_null($v) || (is_bool($v) && $v === false) ? $setting['value'] : $v;
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
    return $this->settingModel->insert([
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
    return $this->settingModel->where("name", $name)->update([
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
      $this->settingModel->where("name", $name)->update([
        "value" => serialize($value),
        "updatedAt" => time()
      ]);
    }
    return true;
  }
}
