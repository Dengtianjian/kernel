<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\FileSystem\Path;

/**
 * DiscuzX 平台路径（{@see Path} 的 DiscuzX 实现）
 *
 * kernel 以**插件**形式运行在 DiscuzX 中，目录布局与「独立项目」不同。本类继承 `Path`，
 * 在能覆盖的方法上给出 DiscuzX 语义的实现；父类没有对应概念的目录以新方法补充。
 * 全部为纯静态 getter：实时推导、无缓存、无副作用、不定义任何常量。
 *
 * ## 覆盖情况一览
 *
 * | 父类方法 | 是否覆盖 | DiscuzX 取值 |
 * |---|---|---|
 * | `projectRoot()` | 否（父类已含 DiscuzX 分支） | `DISCUZ_ROOT` 去尾斜杠 |
 * | `kernelRoot()` | 否（与部署位置无关，永远正确） | `{projectRoot}/source/plugin/gstudio_kernel` |
 * | `root()` | 否（父类推导在插件布局下恰好正确） | `{projectRoot}/source/plugin/{appId}` |
 * | `kernelDir()` | 否（父类推导正确） | `source/plugin/gstudio_kernel` |
 * | `data()` | **是** | `{projectRoot}/data/plugindata/{appId}`（Discuz 插件数据惯例） |
 * | `storage()` | **是** | `{data()}/Storage`（用户文件放 Discuz data 下，不写进插件目录） |
 * | `dir()` | **是**（并修正父类"两个变量同值"的笔误） | `source/plugin/{appId}` |
 * | `join()` / `optimizedPath()` | 否（与平台无关） | — |
 * | `relativePath()` | **无法覆盖**（父类为 `private`，不参与继承） | 本类另提供 public 版本供模块内使用 |
 *
 * ## 新增方法（父类无对应概念）
 *
 * | 方法 | 含义 | 示例 |
 * |---|---|---|
 * | `pluginRoot()` | 插件目录（绝对路径） | `{projectRoot}/source/plugin` |
 * | `plugin()` | 插件目录（相对路径） | `source/plugin` |
 * | `dataRoot()` | Discuz data 目录（绝对路径） | `{projectRoot}/data` |
 *
 * ## 使用前提（重要）
 *
 * PHP 的静态方法调用按**书写时的类名**解析，不存在运行期"路径提供者替换"：
 * kernel 内部（`Log`、`Provisioner`、`LocalStorage`、`FileSystem` 等）写的是 `Path::data()`，
 * **不会**因为存在本子类而改走这里。因此本类的覆盖只对**显式调用 `DiscuzXPath::xxx()`** 的
 * 代码（本模块内）生效；若要让 kernel 内部也走 DiscuzX 语义，需要 kernel 侧支持可替换的
 * 路径提供者（不在本模块可改范围）。
 *
 * 用法：
 * ```php
 * DiscuzXPath::data();       // {DISCUZ_ROOT}/data/plugindata/{appId}
 * DiscuzXPath::storage();    // {DISCUZ_ROOT}/data/plugindata/{appId}/Storage
 * DiscuzXPath::dir();        // source/plugin/{appId}
 * DiscuzXPath::pluginRoot(); // {DISCUZ_ROOT}/source/plugin
 * ```
 */
class DiscuzXPath extends Path
{
  /**
   * 应用数据目录（绝对路径）—— 覆盖父类
   *
   * DiscuzX 惯例是把插件数据放在站点 `data/plugindata/{appId}` 下，
   * 而不是插件自身目录内（插件目录可能在升级/覆盖时被整体替换）。
   *
   * @return string|null 形如 `{projectRoot}/data/plugindata/{appId}`；未实例化 App 时返回 null
   */
  public static function data()
  {
    $appId = App::id();
    if ($appId === null) {
      return null;
    }
    return self::join(self::projectRoot(), "data", "plugindata", $appId);
  }

  /**
   * 应用存储目录（绝对路径）—— 覆盖父类
   *
   * 与 {@see data()} 保持一致，落在 Discuz data 目录下（而非插件目录内的 `Storage`）。
   *
   * @return string|null 形如 `{projectRoot}/data/plugindata/{appId}/Storage`
   */
  public static function storage()
  {
    $data = self::data();
    return $data === null ? null : self::join($data, "Storage");
  }

  /**
   * 应用目录（相对路径，相对项目根）—— 覆盖父类
   *
   * 注：父类实现中 `$root` 与 `$appRoot` 均取 `self::root()`，导致结果恒为空串；
   * 这里按「应用根相对项目根」的本意推导。
   *
   * @return string|null 形如 `source/plugin/{appId}`
   */
  public static function dir()
  {
    $appRoot = self::root();
    if ($appRoot === null) {
      return null;
    }
    return self::relativePath($appRoot, (string) self::projectRoot());
  }

  /**
   * 求 $path 相对 $base（绝对路径前缀）的路径
   *
   * 父类同名方法为 `private`，不参与继承、无法覆盖，这里提供 public 版本供本模块使用
   * （逻辑与父类一致：截掉前缀并把分隔符规范化为当前系统的 DIRECTORY_SEPARATOR）。
   *
   * @param string $path 绝对路径
   * @param string $base 绝对路径前缀
   * @return string 相对路径（已去掉前导分隔符）
   */
  public static function relativePath(string $path, string $base): string
  {
    $relative = substr($path, strlen($base));
    return ltrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
  }

  /**
   * 插件目录（绝对路径）
   *
   * @return string 形如 `{projectRoot}/source/plugin`
   */
  public static function pluginRoot(): string
  {
    return (string) self::join(self::projectRoot(), "source", "plugin");
  }

  /**
   * 插件目录（相对路径，相对项目根）
   *
   * @return string 固定为 `source/plugin`
   */
  public static function plugin(): string
  {
    return "source/plugin";
  }

  /**
   * Discuz data 目录（绝对路径）
   *
   * 注意：与覆盖后的 {@see data()} 不同——`data()` 是**当前插件**的数据目录，
   * 本方法返回的是站点 `data` 根目录。
   *
   * @return string 形如 `{projectRoot}/data`
   */
  public static function dataRoot(): string
  {
    return (string) self::join(self::projectRoot(), "data");
  }
}
