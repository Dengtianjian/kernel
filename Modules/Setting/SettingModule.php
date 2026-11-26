<?php

namespace kernel\Modules\Setting;

use kernel\Foundation\App;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Discuz\Foundation\DiscuzApp;

/**
 * 设置服务
 *
 * 键值对设置项的读写入口：值经 serialize() 落库，读取时反序列化，
 * 并区分「字符串 false」与「布尔 false」。持久化交给同目录的 {@see SettingsModel}，
 * 本类只做编解码与字段维护（`updated_at`）。
 *
 * 由 {@see \kernel\Modules\Setting\Setting} 绑定为应用单例：每个应用各自一份，
 * 因此「给哪个应用加设置」由容器决定，不靠全局状态推断。
 */
class SettingModule extends AbilityBaseObject
{
  /**
   * 本次请求已解码的设置项
   *
   * null 表示尚未加载；加载后 name => value 直接复用，避免同一请求重复查库。
   *
   * @var array|null
   */
  protected $cachedItems = null;

  /**
   * syscache 缓存键
   *
   * 按应用隔离，避免多应用共用同一份设置缓存。
   *
   * @return string
   */
  private function cacheKey()
  {
    $appId = preg_replace("/[^A-Za-z0-9_]/", "_", (string) $this->app()->appId());

    return "gstudio_settings_{$appId}";
  }

  /**
   * 从数据库读出全部设置项并解码
   *
   * @return array name => value
   */
  private function queryAll()
  {
    $settings = [];
    foreach ($this->model()->get() as $item) {
      $settings[$item['name']] = $item['value'] ? $this->decodeValue($item['value']) : null;
    }

    return $settings;
  }

  /**
   * 读取全部设置项（请求内缓存 → Discuz syscache → 数据库）
   *
   * Discuz 环境命中 syscache 时不再查 settings 表；未命中回源一次并写回。
   * 非 Discuz 环境只在本次请求内复用。
   *
   * @return array name => value
   */
  protected function allItems()
  {
    if ($this->cachedItems !== null) {
      return $this->cachedItems;
    }

    $isDiscuz = DiscuzApp::exists();
    $cacheKey = $this->cacheKey();
    if ($isDiscuz && function_exists("loadcache") && function_exists("getglobal")) {
      \loadcache($cacheKey);
      $cached = \getglobal("cache/{$cacheKey}");
      if (is_array($cached)) {
        return $this->cachedItems = $cached;
      }
    }

    $this->cachedItems = $this->queryAll();
    if ($isDiscuz && function_exists("save_syscache")) {
      \save_syscache($cacheKey, $this->cachedItems);
    }

    return $this->cachedItems;
  }

  /**
   * 写入后重建缓存
   *
   * @return array
   */
  private function refreshCache()
  {
    $this->cachedItems = $this->queryAll();
    if (DiscuzApp::exists() && function_exists("save_syscache")) {
      \save_syscache($this->cacheKey(), $this->cachedItems);
    }

    return $this->cachedItems;
  }
  /**
   * 构造即装配
   *
   * 登记自己：日志名用 `self::class`（基类名），这样子类实例也能按基类名取到；
   * 短名（`$app->setting()`）由 Bootstrap 在登记时决定。
   *
   * @return void
   */
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
    $value = @unserialize($raw, ["allowed_classes" => false]);
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

    $allItems = $this->allItems();
    $settings = [];
    foreach ($names as $name) {
      if (array_key_exists($name, $allItems)) {
        $settings[$name] = $allItems[$name];
      }
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
    $allItems = $this->allItems();

    return array_key_exists($name, $allItems) ? $allItems[$name] : null;
  }
  /**
   * 查询某个设置项是否存在
   *
   * @param string $name 设置项名称
   * @return bool
   */
  public function exists($name)
  {
    return array_key_exists($name, $this->allItems());
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
    $result = $this->model()->insert([
      "name" => $name,
      "value" => $serialization ? serialize($value) : $value,
      "updated_at" => time(),
    ]);
    if ($result) {
      $this->refreshCache();
    }

    return $result;
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
    $result = $this->model()->where("name", $name)->update([
      "value" => $serialization ? serialize($value) : $value,
      "updated_at" => time(),
    ]);
    if ($result) {
      $this->refreshCache();
    }

    return $result;
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
    $this->refreshCache();

    return true;
  }
}
