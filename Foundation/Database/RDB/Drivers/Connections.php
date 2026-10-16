<?php

namespace kernel\Foundation\Database\RDB\Drivers;

use kernel\Foundation\Database\RDB\Drivers\PDO\PdoDriver;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 关系型数据库连接管理器
 *
 * Connections 是关系型数据库连接池的全局管理中心，通过静态属性维护所有 {@see AbstractDriver} 实例，
 * 支持在运行时动态注册和切换活跃数据库连接。所有方法均为静态方法，全局共享状态。
 *
 * ## 核心概念
 *
 * ```
 * drivers:     default → AbstractDriver(主库)
 *              slave   → AbstractDriver(只读从库)
 *              log     → AbstractDriver(日志库)
 * useDriver:   → AbstractDriver(主库)  ← 当前活跃连接
 * ```
 *
 * ## 典型用法
 *
 * ```php
 * // 初始化阶段注册连接
 * Connections::addDriver((new PdoDriver())->mysql(...), 'master', true);
 * Connections::addDriver((new PdoDriver())->mysql(...), 'slave');
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
 * @see AbstractDriver 驱动抽象基类
 * @see \kernel\Foundation\Database\RDB\DB 数据库门面（通过 connection() 间接使用 Connections）
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
   * @var AbstractDriver
   */
  private static $useDriver = null;
  /**
   * 默认数据库驱动
   * @var AbstractDriver
   */
  private static $defaultDriver = null;
  /**
   * 添加驱动
   * @param AbstractDriver $driver    驱动实例
   * @param string         $name      驱动在驱动列表中的键名
   * @param boolean        $isDefault 是否是默认使用的驱动
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
   * @throws Error
   * @return bool
   */
  static function useDriver($name)
  {
    if (!array_key_exists($name, self::$drivers)) {
      throw new Error("使用的数据库驱动不存在", 500, "useDatabaseDriverNotExist:500");
    }
    self::$useDriver = self::$drivers[$name];

    return true;
  }
  /**
   * 获取当前正在使用的数据库驱动
   * @return AbstractDriver
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
   * 按名获取单个数据库驱动，**不改变**当前连接
   *
   * 与 {@see useDriver()} 的区别：后者会切换全局当前连接，本方法只取驱动。
   * 供「某个模型 / 某张表固定使用某个驱动」这类按名解析的声明式场景使用。
   *
   * @param string $name 驱动在数据库驱动列表中的键名
   * @throws Error
   * @return AbstractDriver
   */
  static function getDriver($name)
  {
    if (!array_key_exists($name, self::$drivers)) {
      throw new Error("获取的数据库驱动不存在", 500, "getDatabaseDriverNotExist:500");
    }

    return self::$drivers[$name];
  }
  /**
   * 设置默认数据库驱动
   * @param mixed $name 驱动在数据库驱动列表中的键名
   * @throws Error
   */
  static function setDefaultDriver($name = "default")
  {
    if (!array_key_exists($name, self::$drivers)) {
      throw new Error("设置的数据库驱动不存在", 500, "setDefaultDatabaseDriverError:500");
    }
    self::$defaultDriver = self::$drivers[$name];
  }
  /**
   * 获取当前设置的默认驱动
   * @return AbstractDriver
   */
  static function getDefaultDriver()
  {
    return self::$defaultDriver;
  }
  /**
   * 切换回默认数据库驱动
   * @throws Error
   */
  static function switchToDefaultDriver()
  {
    $defaultDriver = self::$defaultDriver;
    if (count(self::$drivers) === 0) {
      throw new Error("切换回默认数据库失败", 500, "switchToDefaultDatabaseDriverError:500");
    }
    // 回退优先级（见类注释）：已注册的默认驱动 → 名为 "default" 的驱动 → 第一个注册的驱动。
    // 已设默认驱动时不得再回退，否则显式指定的默认会被"第一个注册的"覆盖。
    if (!$defaultDriver) {
      $defaultDriver = array_key_exists("default", self::$drivers)
        ? self::$drivers['default']
        : Arr::first(self::$drivers);
    }
    self::$useDriver = $defaultDriver;
  }
}
