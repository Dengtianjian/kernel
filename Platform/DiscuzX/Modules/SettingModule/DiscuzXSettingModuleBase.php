<?php

namespace kernel\Platform\DiscuzX\Modules\SettingModule;

use kernel\Modules\SettingModule\SettingModuleBase;

class DiscuzXSettingModuleBase extends SettingModuleBase
{
  /**
   * 设置项模型实例
   *
   * @var DiscuzXSettingsModel
   */
  protected $SettingModelInstance = null;

  protected $publicNames = [];
  protected $groupNames = [];
  protected $adminNames = [];

  /**
   * DiscuzX平台的通用设置存储模块
   *
   * @param array $names 都可以获取的键名
   * @param array $groupNames 不同用户组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
   * @param array $adminNames 不同管理组可以获取的键名，键是数组ID，值是键名数组 [ 1=>['appId','appName'],3=>['appName'] ]
   * @return void
   */
  public function __construct(DiscuzXSettingsModel $SettingsModel, $publicNames = [], $groupNames = [], $adminNames = [])
  {
    $this->SettingModelInstance = $SettingsModel;

    $this->publicNames = $publicNames;
    $this->groupNames = $groupNames;
    $this->adminNames = $adminNames;
  }
  public function filterName(...$names)
  {
    global $_G;

    $publicNames = $this->publicNames;
    if (isset($this->groupNames[$_G['groupid']])) {
      $publicNames = array_merge($names, $this->groupNames[$_G['groupid']]);
    }

    if (isset($this->adminNames[$_G['adminid']])) {
      $publicNames = array_merge($names, $this->adminNames[$_G['adminid']]);
    }

    return array_unique(array_intersect($names, $publicNames));
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
    return (is_bool($v) && $v === false) && strpos($setting['value'], "b:") === false ? $setting['value'] : $v;
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
