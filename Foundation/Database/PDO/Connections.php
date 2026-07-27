<?php
namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Database\PDO\Driver;
use kernel\Foundation\Exception\RuyiException;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 多数据库连接管理器
 *
 * Connections 是数据库连接池的全局管理中心，通过静态属性维护所有 Driver 实例，
 * 支持在运行时动态注册和切换活跃数据库连接。所有方法均为静态方法，全局共享状态。
 *
 * ## 核心概念
 *
 * ```
 * drivers:     default → Driver(主库)
 *              slave   → Driver(只读从库)
 *              log     → Driver(日志库)
 * useDriver:   → Driver(主库)  ← 当前活跃连接
 * ```
 *
 * ## 典型用法
 *
 * ```php
 * // 初始化阶段注册连接
 * Connections::addDriver(new Driver(...), 'master', true);
 * Connections::addDriver(new Driver(...), 'slave');
 *
 * // 运行时切到从库读
 * Connections::useDriver('slave');
 * $users = DB::table('users')->get();
 *
 * // 切回主库写
 * Connections::switchToDefaultDriver();
 * DB::table('users')->insert($data);
 * ```
 *
 * ## 自动回退
 *
 * `getUseDriver()` 在无活跃连接时自动调用 `switchToDefaultDriver()`，
 * 回退优先级：`$defaultDriver` → 名为 "default" 的驱动 → 第一个注册的驱动。
 *
 * @see Driver PDO 驱动封装
 * @see DB    数据库门面（通过 connection() 间接使用 Connections）
 */
class Connections extends AbilityBaseObject
{
  /**
   * 数据库驱动列表
   * @var array
   */
  private static $drivers = [];
  /**
   * 当前使用的数据库驱动
   * @var Driver
   */
  private static $useDriver = null;
  /**
   * 默认数据库驱动
   * @var Driver
   */
  private static $defaultDriver = null;
  /**
   * 添加驱动
   * @param Driver  $driver    驱动实例
   * @param string  $name      驱动在驱动列表中的键名
   * @param boolean $isDefault 是否是默认使用的驱动
   */
  static function addDriver($driver, $name = "default", $isDefault = false)
  {
    self::$drivers[$name] = $driver;
    if ($isDefault) {
      self::setDefaultDriver($name);
    }
  }
  /**
   * 使用数据库驱动
   * @param string $name 驱动列表键名
   * @throws \kernel\Foundation\Exception\RuyiException
   * @return bool
   */
  static function useDriver($name)
  {
    if (!array_key_exists($name, self::$drivers)) {
      throw new RuyiException("使用的数据库驱动不存在", 500, "databaseStaticDriverNotExist:500");
    }
    self::$useDriver = self::$drivers[$name];

    return true;
  }
  /**
   * 获取当前正在使用的数据库驱动
   * @return Driver
   */
  static function getUseDriver()
  {
    if (!self::$useDriver) {
      self::switchToDefaultDriver();
    }
    return self::$useDriver;
  }
  /**
   * 获取数据库驱动列表
   * @return array
   */
  static function getDrivers()
  {
    return self::$drivers;
  }
  /**
   * 设置默认数据库驱动
   * @param mixed $name 驱动在数据库驱动列表中的键名
   * @throws \kernel\Foundation\Exception\RuyiException
   */
  static function setDefaultDriver($name = "default")
  {
    if (!array_key_exists($name, self::$drivers)) {
      throw new RuyiException("设置的数据库驱动不存在", 500, "setDefaultDatabaseDriverError:500");
    }
    self::$defaultDriver = self::$drivers[$name];
  }
  /**
   * 获取当前设置的默认驱动
   * @return Driver
   */
  static function getDefaultDriver()
  {
    return self::$defaultDriver;
  }
  /**
   * 切换回默认数据库驱动
   * @throws \kernel\Foundation\Exception\RuyiException
   */
  static function switchToDefaultDriver()
  {
    $defaultDriver = self::$defaultDriver;
    if (count(self::$drivers) === 0) {
      throw new RuyiException("切换回默认数据库失败", 500, "switchToDefaultDatabaseDriverError:500");
    }
    if (!$defaultDriver && array_key_exists("default", self::$drivers)) {
      $defaultDriver = self::$drivers['default'];
    } else {
      $defaultDriver = self::$drivers[array_key_first(self::$drivers)];
    }
    self::$useDriver = $defaultDriver;
  }
}