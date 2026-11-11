<?php

namespace kernel\Providers\Setting;

use kernel\Foundation\App;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 设置服务
 *
 * 键值对设置项的读写入口：值经 serialize() 落库，读取时反序列化，
 * 并区分「字符串 false」与「布尔 false」。持久化交给同目录的 {@see SettingsModel}，
 * 本类只做编解码与字段维护（`updated_at`）。
 *
 * 由 {@see \kernel\Providers\Setting\SettingProvider} 绑定为应用单例：每个应用各自一份，
 * 因此「给哪个应用加设置」由容器决定，不靠全局状态推断。
 */
class SettingProvider extends AbilityBaseObject
{
  /**
   * 构造即装配
   *
   * 登记自己：日志名用 `self::class`（基类名），这样子类实例也能按基类名取到；
   * 短名（`$app->setting()`）由 Bootstrap 在登记时决定。
   *
   * @return void
   */
  public function __construct()
  {
    $this->app()->provider($this, self::class);
  }

  /**
   * 本应用的表访问入口
   *
   * 每次调用新建一个**绑定到本服务所属应用**的模型：模型是「一次查询一条链」的轻量对象，
   * 表名在构造时按绑定的应用解析，因此 download 读 super_app 的设置才对得上表。
   *
   * @return SettingsModel
   */
  public function model()
  {
    //* 走容器：构造参数由容器注入本应用，归属不必手传
    return $this->app()->make(SettingsModel::class);
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
   * 未传入任何名称时返回空数组：空名称列表继续下传会拼出 `` `name` IN () `` 这种非法 SQL。
   *
   * @param array ...$names 设置项名称数组
   * @return array 键是设置项名称，值是设置项值
   */
  public function items(...$names)
  {
    if (!$names) {
      return [];
    }

    $settingsData = $this->model()->where("name", $names)->get();
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
    $setting = $this->model()->where("name", $name)->first();
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
    return $this->model()->where("name", $name)->exists();
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
    return $this->model()->insert([
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
    return $this->model()->where("name", $name)->update([
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
      $this->model()->where("name", $name)->update([
        "value" => serialize($value),
        "updated_at" => time(),
      ]);
    }
    return true;
  }
}
