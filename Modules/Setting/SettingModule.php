<?php

namespace kernel\Modules\Setting;

use kernel\Foundation\Module\Module;

class SettingModule extends Module
{
  /**
   * 设置项模型实例
   *
   * @var SettingsModel
   */
  protected $model = null;
  public function __construct($settingsModel = null)
  {
    $this->model = $settingsModel ?: new SettingsModel();
  }
  /**
   * 反序列化设置值
   *
   * 存储时统一 serialize()，读取时还原；兼容「字符串 false」与「布尔 false」区分：
   * 当反序列化结果为 false 且原始串不以 `b:` 开头时，说明原值就是字符串 "false"，
   * 直接返回原始串，避免被误判为空。
   *
   * @param string|null $raw 库中的原始字符串
   * @return mixed 还原后的值；空值返回 null
   */
  protected function decodeValue($raw)
  {
    if ($raw === null || $raw === "") {
      return null;
    }
    $value = @unserialize($raw);
    if ($value === false && strpos($raw, "b:") === false) {
      return $raw;
    }
    return $value;
  }
  /**
   * 获取多个设置项
   *
   * @param array ...$names 设置项名称数组
   * @return array 键是设置项名称，值是设置项值
   */
  public function items(...$names)
  {
    $settingsData = $this->model->where("name", $names)->get();
    $settings = [];
    foreach ($settingsData as $item) {
      $settings[$item['name']] = $item['value'] ? $this->decodeValue($item['value']) : null;
    }
    return $settings;
  }
  /**
   * 获取单个设置项值
   *
   * @param string $name 设置项名称
   * @return mixed 设置项值
   */
  public function item($name)
  {
    $setting = $this->model->where("name", $name)->first();
    if (!$setting) {
      return null;
    }
    return $this->decodeValue($setting['value'] ?? null);
  }
  /**
   * 查询某个设置项是否存在
   *
   * @param string $name 设置项名称
   * @return bool
   */
  public function exists($name)
  {
    return $this->model->where("name", $name)->exists();
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
    return $this->model->insert([
      "name" => $name,
      "value" => $serialization ? serialize($value) : $value,
      "updated_at" => time(),
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
    return $this->model->where("name", $name)->update([
      "value" => $serialization ? serialize($value) : $value,
      "updated_at" => time(),
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
      $this->model->where("name", $name)->update([
        "value" => serialize($value),
        "updated_at" => time(),
      ]);
    }
    return true;
  }
}
