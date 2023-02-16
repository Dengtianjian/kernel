<?php

namespace kernel\Service;

use kernel\Model\SettingsModel;

class SettingService
{
  /**
   * 设置表模型
   *
   * @var SettingsModel
   */
  public $settingModel = null;
  public function __construct()
  {
    $this->settingModel = new SettingsModel();
  }
  private static $instance = null;
  /**
   * 快速调用，单例模式
   *
   * @return SettingService
   */
  public static function quick()
  {
    if (!self::$instance) {
      self::$instance = new SettingService();
    }
    return self::$instance;
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
        $Settings[$item['name']] = unserialize($item['value']);
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
   *
   * @param string $name 设置项名称
   * @return mixed 设置项值
   */
  public function get($name)
  {
    $setting = $this->settingModel->where("name", $name)->getOne();
    if (!$setting) return null;
    return unserialize($setting['value']);
  }
  /**
   * 添加设置项
   *
   * @param string $name 设置项名称
   * @param mixed $value 设置项值
   * @return bool
   */
  public function add($name, $value = null)
  {
    return $this->settingModel->insert([
      "name" => $name,
      "value" => serialize($value)
    ]);
  }
  /**
   * 保存单个设置项
   *
   * @param string $name 设置项名称
   * @param string $value 设置项值
   * @return bool
   */
  public function save($name, $value)
  {
    return $this->settingModel->where("name", $name)->update([
      "value" => serialize($value)
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
    $values = array_values($settings);
    foreach ($values as &$item) {
      $item = serialize($item);
    }
    return $this->settingModel->batchUpdate(array_keys($settings), $values);
  }
}
