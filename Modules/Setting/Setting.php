<?php

namespace kernel\Modules\Setting;

use kernel\Foundation\Facade;

/**
 * 设置模块门面（Facade，单例）
 *
 * 静态入口，将调用转发到单例的 SettingModule 实例。底层实例由 resolve() 懒创建并缓存，
 * 全应用共享同一实例，无需手动构造。
 *
 * @method static array items(...$names) 获取多个设置项（返回 `["name" => value]`）
 * @method static mixed item($name) 获取单个设置项值
 * @method static bool exist($name) 设置项是否存在
 * @method static bool add($name, $value = null, $serialization = true) 添加设置项
 * @method static bool save($name, $value, $serialization = true) 保存单个设置项
 * @method static bool saveItems($settings) 批量保存设置项（`["name" => value]`）
 */
class Setting extends Facade
{
  /**
   * 兜底实例工厂（单例门面）
   *
   * 首次访问时创建 SettingModule 并缓存到门面注册表，后续复用同一实例。
   *
   * @return object
   */
  protected static function resolve(): object
  {
    return new SettingModule(new SettingsModel());
  }
}
