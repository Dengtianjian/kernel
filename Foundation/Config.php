<?php

namespace kernel\Foundation;


use kernel\Foundation\Data\Arr;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\FileSystem;

/**
 * 配置管理类
 *
 * 提供多应用配置的读取、写入、存在性判断、删除、追加和清空功能。
 * 全部方法均为静态方法，配置按 $appId 隔离缓存在内存中。
 *
 * 键路径支持 `.` 和 `/` 两种层级分隔符，如 "database.mysql.host" 等价于 "database/mysql/host"。
 *
 * @package kernel\Foundation
 */
class Config
{
  /**
   * 内存配置缓存，按应用 ID 分组存储
   *
   * @var array<string, array>
   */
  private static $configs = [];

  /**
   * 初始化配置：加载应用 Configs 目录下的多层配置文件
   *
   * 由 App 构造时 `new Config;` 触发（App::initConfig() 已迁移至本构造方法）。
   *
   * 读取顺序（存在才读取）：
   * default(Config.php) -> development(Config.development.php) -> local(Config.local.php)
   * -> production(Config.production.php) -> release(Config.release.php)
   * 后读取的文件覆盖先读取的文件（release 覆盖 production 覆盖 local 覆盖 development 覆盖 default）。
   *
   * @return void
   */
  function __construct()
  {
    $configFilesDir = FileHelper::combinedFilePath(FileSystem::root(), "Configs");
    if (!is_dir($configFilesDir))
      return;

    $readConfigFiles = ["Config.php", "Config.development.php", "Config.local.php", "Config.production.php", "Config.release.php"];

    foreach ($readConfigFiles as $configFileName) {
      self::read(FileHelper::combinedFilePath($configFilesDir, $configFileName));
    }
  }

  /**
   * 解析键路径，将 . 统一转为 / 后拆分为层级数组
   *
   * @param string $key 键路径，支持 / 或 . 分隔
   * @return array 层级数组
   */
  private static function parseKey($key)
  {
    return \explode("/", \str_replace(".", "/", $key));
  }

  /**
   * 读取应用配置文件并合并到内存配置中
   *
   * 配置文件直接 return 一个配置数组即可，无需以 appId 作为顶级键。
   * read() 会根据 $appId 自动将配置归类到对应的应用下，并使用 Arr::merge() 深度合并。
   *
   * @param string|null $filePath 配置文件完整路径，为 null 或文件不存在时直接返回 false
   * @param string $appId 应用标识，决定配置写入哪个应用的分组，默认为当前应用
   * @return bool 成功返回 true，失败返回 false
   */
  static function read($filePath = null, $appId = null)
  {
    $appId ??= App::id();
    if (!$filePath || !\file_exists($filePath)) {
      return false;
    }
    $configs = include($filePath);
    if (!is_array($configs)) {
      return false;
    }
    if (!isset(self::$configs[$appId])) {
      self::$configs[$appId] = [];
    }

    self::$configs[$appId] = Arr::merge(self::$configs[$appId], $configs);
    return true;
  }
  /**
   * 获取配置项
   *
   * 用 `.` 或 `/` 分隔逐层深入查找，任一层级不存在即返回 $defaultValue。
   * 传 null 可获取当前应用的全部配置。
   *
   * 示例：
   *   Config::get("database.mysql.host")      // 点号，返回 "localhost"
   *   Config::get("database/mysql/host")      // 斜线（等价）
   *   Config::get("app.debug", false)         // 带默认值
   *   Config::get()                           // 获取全部配置
   *
   * @param string|null $key 配置键路径，支持 / 或 . 分隔层级。null 返回全部配置
   * @param mixed $defaultValue 缺省值，配置键路径中任一层级不存在或 appId 未加载时返回此值
   * @param string $appId 应用标识
   * @return mixed 匹配的配置值，或全部配置数组（$key 为 null 时），或 $defaultValue
   */
  static function get($key = null, $defaultValue = null, $appId = null)
  {
    $appId ??= App::id();
    if (!isset(self::$configs[$appId])) {
      return $defaultValue;
    }
    $configs = self::$configs[$appId];
    if ($key === null) {
      return $configs;
    }
    $parts = self::parseKey($key);
    foreach ($parts as $part) {
      if (!isset($configs[$part])) {
        return $defaultValue;
      }
      $configs = $configs[$part];
    }
    return $configs;
  }
  /**
   * 运行时设置配置值（仅内存，不写入文件）
   *
   * 支持两种调用模式：
   *
   * 1. 数组合并 — 用于批量覆盖，使用 Arr::merge() 深度合并到现有配置。此时 $value 必须不传或为 null：
   *      Config::set(["mode" => "debug"]);
   *
   * 2. 键值设置 — 支持点号或斜线路径，中间节点不存在时自动创建空数组。可将值显式设为 null：
   *      Config::set("database.mysql.host", "127.0.0.1");
   *      Config::set("app/debug", true);
   *      Config::set("feature.flag", null);      // 显式设为 null
   *
   * @param string|array $keyOrValue 键名（支持 / 或 . 分隔路径）或整个配置数组
   * @param mixed        $value       键值，传 null 且第一个参数为数组时进入数组合并模式
   * @param string       $appId       应用标识
   * @return void
   */
  static function set($keyOrValue, $value = null, $appId = null)
  {
    $appId ??= App::id();
    if (!isset(self::$configs[$appId])) {
      self::$configs[$appId] = [];
    }
    // 模式一：数组合并（$value 未传且第一个参数是数组）
    if (func_num_args() < 2 && is_array($keyOrValue)) {
      self::$configs[$appId] = Arr::merge(self::$configs[$appId], $keyOrValue);
      return;
    }
    // 模式二：键值设置
    $parts = self::parseKey($keyOrValue);
    $lastKey = array_pop($parts);
    $config = &self::$configs[$appId];
    foreach ($parts as $part) {
      if (!isset($config[$part]) || !is_array($config[$part])) {
        $config[$part] = [];
      }
      $config = &$config[$part];
    }
    $config[$lastKey] = $value;
  }
  /**
   * 判断指定配置键是否存在
   *
   * 用于区分「键不存在」与「键存在但值为 null」的场景，弥补 get() 无法区分两者的不足。
   * appId 未加载时返回 false。
   *
   * 示例：
   *   Config::has("app.debug")          // 检查调试模式是否已配置
   *   Config::has("database.mysql")     // 检查数据库配置段是否存在
   *
   * @param string $key 配置键路径，支持 / 或 . 分隔层级
   * @param string $appId 应用标识
   * @return bool 键存在返回 true，不存在或 appId 未加载返回 false
   */
  static function has($key, $appId = null)
  {
    $appId ??= App::id();
    if (!isset(self::$configs[$appId])) {
      return false;
    }
    $configs = self::$configs[$appId];
    $parts = self::parseKey($key);
    foreach ($parts as $part) {
      if (!isset($configs[$part])) {
        return false;
      }
      $configs = $configs[$part];
    }
    return true;
  }
  /**
   * 删除指定配置键
   *
   * 键或其中间层级不存在时静默返回，不会抛异常。仅删除目标键本身，不影响同级或上级节点。
   *
   * 示例：
   *   Config::forget("app.debug");       // 移除调试开关
   *   Config::forget("temp.runtime");    // 清除已完成的运行时临时配置
   *
   * @param string $key 配置键路径，支持 / 或 . 分隔层级
   * @param string $appId 应用标识
   * @return void
   */
  static function forget($key, $appId = null)
  {
    $appId ??= App::id();
    if (!isset(self::$configs[$appId])) {
      return;
    }
    $parts = self::parseKey($key);
    $lastKey = array_pop($parts);
    $config = &self::$configs[$appId];
    foreach ($parts as $part) {
      if (!isset($config[$part]) || !is_array($config[$part])) {
        return;
      }
      $config = &$config[$part];
    }
    unset($config[$lastKey]);
  }
  /**
   * 向数组类型的配置项追加值
   *
   * 若目标键不存在或不是数组，自动创建空数组后再追加。中间节点不存在时同样自动创建。
   *
   * 示例：
   *   Config::push("cors.allowOrigin", "https://new-domain.com");
   *   Config::push("dingtalk.receivers", "user123");
   *
   * @param string $key    配置键路径，支持 / 或 . 分隔层级
   * @param mixed  $value  要追加的值，可以是标量、数组或对象
   * @param string $appId  应用标识
   * @return void
   */
  static function push($key, $value, $appId = null)
  {
    $appId ??= App::id();
    if (!isset(self::$configs[$appId])) {
      self::$configs[$appId] = [];
    }
    $parts = self::parseKey($key);
    $lastKey = array_pop($parts);
    $config = &self::$configs[$appId];
    foreach ($parts as $part) {
      if (!isset($config[$part]) || !is_array($config[$part])) {
        $config[$part] = [];
      }
      $config = &$config[$part];
    }
    if (!isset($config[$lastKey]) || !is_array($config[$lastKey])) {
      $config[$lastKey] = [];
    }
    $config[$lastKey][] = $value;
  }
  /**
   * 清空指定应用的全部配置
   *
   * 清空后再次 get() 将返回 $defaultValue，set()/push() 会重新初始化空数组。
   * 常用于测试 tearDown 或长时间运行进程中卸载已失效的应用配置。
   *
   * 示例：
   *   Config::flush();            // 清空当前应用
   *   Config::flush("otherApp");  // 清空指定应用
   *
   * @param string $appId 应用标识
   * @return void
   */
  static function flush($appId = null)
  {
    $appId ??= App::id();
    unset(self::$configs[$appId]);
  }
  /**
   * 清空所有应用的全部配置
   *
   * 常用于测试 setUp 中完全重置配置状态，确保每个测试用例从干净的配置开始。
   *
   * 示例：
   *   Config::flushAll();
   *   // 等价于 self::$configs = [];
   *
   * @return void
   */
  static function flushAll()
  {
    self::$configs = [];
  }
}
