<?php

namespace kernel\Providers\App;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 配置（每个应用一份）
 *
 * 应用侧一律 `$app->config()`：拿到的是**本应用**的配置实例，读的是本应用目录下
 * `Configs/` 的五层文件；容器里是同一个对象（`make(Config::class)` 与 `$app->config()` 一致）。
 *
 * 归属由容器注入（{@see AbilityBaseObject::app()}），本类不持有也不推断应用 ID：
 * - 无构造参数，归属只来自容器——容器构造完实例才 `bindApp()`，所以配置在**首次访问**时
 *   才读文件（{@see ensureLoaded()}），构造期读会落到默认应用；
 * - 配置读哪个目录，只由「哪个应用的容器构造了它」决定；
 * - 跨应用读配置必须显式走对方应用的容器：`App::of("gstudio_super_app")->config()`；
 * - 没有静态入口 `Config::of()`——组件一律从容器取，否则各组件都能自己造一个「访问别人」的口子。
 *
 * 键路径支持 `.` 与 `/` 两种层级分隔符："database.mysql.host" 等价于 "database/mysql/host"。
 */
class ConfigProvider extends AbilityBaseObject
{
  /**
   * 五层配置文件，按顺序读取，后读的覆盖先读的
   *
   * default → development → local → production → release
   */
  const FILES = [
    "Config.php",
    "Config.development.php",
    "Config.local.php",
    "Config.production.php",
    "Config.release.php",
  ];

  /**
   * 内存配置
   *
   * @var array
   */
  protected $items = [];
  /**
   * 是否已尝试读过配置文件（没有 Configs/ 目录也算读过）
   *
   * @var bool
   */
  protected $loaded = false;

  /**
   * 确保配置已从文件读入（首次访问时触发）
   *
   * 不能放在构造函数里：容器是先 `new` 再 `bindApp()`，构造期 `app()` 只能回落到默认应用，
   * 会读错应用的 `Configs/`。推迟到第一次访问，归属已就位。
   *
   * @return void
   */
  private function ensureLoaded()
  {
    if (!$this->loaded) {
      $this->load();
    }
  }

  /**
   * 加载本应用 `Configs/` 下的五层配置
   *
   * 目录不存在就直接返回（应用可以没有配置文件）；重复调用会重复合并。
   *
   * @return $this
   */
  public function load()
  {
    $this->loaded = true;

    $dir = Path::configs($this->app()->appId());
    if ($dir === null || !is_dir($dir)) {
      return $this;
    }

    foreach (self::FILES as $fileName) {
      $this->read(Path::join($dir, $fileName));
    }

    return $this;
  }

  /**
   * 读取单个配置文件并深度合并进内存配置
   *
   * 配置文件直接 `return` 一个配置数组即可，不需要以 appId 作顶级键。
   *
   * @param string|null $filePath 配置文件完整路径
   * @return bool 读到并合并成功返回 true
   */
  public function read($filePath)
  {
    $this->ensureLoaded();

    if (!$filePath || !\file_exists($filePath)) {
      return false;
    }

    $configs = include($filePath);
    if (!is_array($configs)) {
      return false;
    }

    $this->items = Arr::merge($this->items, $configs);

    return true;
  }

  /**
   * 是否已读过配置文件
   *
   * 只查状态、不触发加载：首次 `get()` / `all()` 等访问时才读文件。
   *
   * @return bool
   */
  public function loaded()
  {
    return $this->loaded;
  }

  /**
   * 获取配置项
   *
   * 用 `.` 或 `/` 分隔逐层深入查找，任一层级不存在即返回 $defaultValue；
   * 传 null 返回本应用的全部配置。
   *
   * @param string|null $key 配置键路径，null 返回全部
   * @param mixed $defaultValue 缺省值
   * @return mixed
   */
  public function get($key = null, $defaultValue = null)
  {
    $this->ensureLoaded();

    if ($key === null) {
      return $this->items;
    }

    $configs = $this->items;
    foreach (self::parseKey($key) as $part) {
      if (!isset($configs[$part])) {
        return $defaultValue;
      }
      $configs = $configs[$part];
    }

    return $configs;
  }

  /**
   * 全部配置
   *
   * @return array
   */
  public function all()
  {
    $this->ensureLoaded();

    return $this->items;
  }

  /**
   * 运行时设置配置值（仅内存，不写文件）
   *
   * 两种模式：
   * 1. 数组合并：只传一个数组，用 Arr::merge() 深度合并；
   * 2. 键值设置：`set("database.mysql.host", "127.0.0.1")`，支持 `.` / `/` 路径，
   *    中间节点不存在时自动建空数组，可显式设成 null。
   *
   * @param string|array $keyOrValue 键名或整个配置数组
   * @param mixed $value 键值
   * @return $this
   */
  public function set($keyOrValue, $value = null)
  {
    $this->ensureLoaded();

    //* 模式一：数组合并
    if (func_num_args() < 2 && is_array($keyOrValue)) {
      $this->items = Arr::merge($this->items, $keyOrValue);

      return $this;
    }

    //* 模式二：键值设置
    $parts = self::parseKey($keyOrValue);
    $lastKey = array_pop($parts);
    $config = &$this->items;
    foreach ($parts as $part) {
      if (!isset($config[$part]) || !is_array($config[$part])) {
        $config[$part] = [];
      }
      $config = &$config[$part];
    }
    $config[$lastKey] = $value;

    return $this;
  }

  /**
   * 判断配置键是否存在
   *
   * 用于区分「键不存在」与「键存在但值为 null」。
   *
   * @param string $key 配置键路径
   * @return bool
   */
  public function has($key)
  {
    $this->ensureLoaded();

    $configs = $this->items;
    foreach (self::parseKey($key) as $part) {
      if (!isset($configs[$part])) {
        return false;
      }
      $configs = $configs[$part];
    }

    return true;
  }

  /**
   * 删除配置键（只删目标节点，不动同级与上级）
   *
   * @param string $key 配置键路径
   * @return $this
   */
  public function forget($key)
  {
    $this->ensureLoaded();

    $parts = self::parseKey($key);
    $lastKey = array_pop($parts);
    $config = &$this->items;
    foreach ($parts as $part) {
      if (!isset($config[$part]) || !is_array($config[$part])) {
        return $this;
      }
      $config = &$config[$part];
    }
    unset($config[$lastKey]);

    return $this;
  }

  /**
   * 向数组类型的配置项追加值
   *
   * 目标键或中间层级不存在、不是数组时，自动建空数组再追加。
   *
   * @param string $key 配置键路径
   * @param mixed $value 要追加的值
   * @return $this
   */
  public function push($key, $value)
  {
    $this->ensureLoaded();

    $parts = self::parseKey($key);
    $lastKey = array_pop($parts);
    $config = &$this->items;
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

    return $this;
  }

  /**
   * 清空本实例的全部配置
   *
   * @return $this
   */
  public function flush()
  {
    $this->ensureLoaded();

    $this->items = [];

    return $this;
  }

  /**
   * 解析键路径：`.` 统一转 `/` 后拆层
   *
   * @param string $key 键路径
   * @return array
   */
  protected static function parseKey($key)
  {
    return \explode("/", \str_replace(".", "/", $key));
  }
}
