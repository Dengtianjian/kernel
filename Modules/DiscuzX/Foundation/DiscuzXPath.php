<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\FileSystem\Path;

/**
 * DiscuzX 平台路径（{@see Path} 的 DiscuzX 实现）
 *
 * kernel 以**插件**形式运行在 DiscuzX 中，目录布局与「独立项目」不同。本类继承 {@see Path}，
 * 在需要改变语义的方法上覆盖父类实现，父类没有对应概念的目录以新方法补充。
 * 全部为纯静态 getter：实时推导、无缓存、无副作用、不定义任何常量。
 *
 * ## 覆盖情况一览
 *
 * | 父类方法 | 是否覆盖 | DiscuzX 取值 |
 * |---|---|---|
 * | {@see project()} | 否（父类已含 DiscuzX 分支） | `DISCUZ_ROOT` 去尾斜杠 |
 * | {@see kernel()} | 否（与部署位置无关，永远正确） | `{project}/source/plugin/gstudio_kernel` |
 * | {@see root()} | 否（父类推导在插件布局下恰好正确） | `{project}/source/plugin/{appId}` |
 * | {@see kernelDir()} | 否（父类推导正确） | `source/plugin/gstudio_kernel` |
 * | {@see data()} | **是** | `{root()}/Data` |
 * | {@see storage()} | **是** | `{pluginData()}/Storage`（用户文件放 Discuz data 下，不写进插件目录） |
 * | {@see dir()} | **是** | `source/plugin/{appId}` |
 * | {@see join()} / {@see optimizedPath()} | 否（与平台无关） | — |
 * | {@see relativePath()} | 否（父类为 `protected`，本类直接继承使用） | — |
 *
 * ## 新增方法（父类无对应概念）
 *
 * | 方法 | 含义 | DiscuzX 取值 |
 * |---|---|---|
 * | {@see plugin()} | 插件目录（绝对路径） | `{project}/source/plugin` |
 * | {@see pluginDir()} | 插件目录（相对路径，相对项目根） | `source/plugin` |
 * | {@see pluginData()} | 本插件的数据目录（Discuz 插件数据惯例） | `{project}/data/plugindata/{appId}` |
 *
 * ## 使用前提（重要）
 *
 * PHP 的静态方法调用按**书写时的类名**解析，不存在运行期「路径提供者替换」：
 * kernel 内部（`Log`、`Provisioner`、`LocalStorage`、`FileSystem` 等）写的是 `Path::data()`，
 * **不会**因为存在本子类而改走这里。因此本类的覆盖只对**显式调用 `DiscuzXPath::xxx()`** 的
 * 代码（本模块内）生效；若要让 kernel 内部也走 DiscuzX 语义，需要 kernel 侧提供可替换的
 * 路径解析层。
 *
 * ## 使用方式
 *
 * ```php
 * DiscuzXPath::pluginData(); // {project}/data/plugindata/{appId}
 * DiscuzXPath::storage();    // {project}/data/plugindata/{appId}/Storage
 * DiscuzXPath::data();       // {project}/source/plugin/{appId}/Data
 * DiscuzXPath::dir();        // source/plugin/{appId}
 * DiscuzXPath::plugin();     // {project}/source/plugin
 * DiscuzXPath::pluginDir();  // source/plugin
 * ```
 */
class DiscuzXPath extends Path
{
  /**
   * 应用数据目录（绝对路径）—— 覆盖 {@see Path::data()}
   *
   * DiscuzX 下应用根即插件目录（`{project}/source/plugin/{appId}`），因此取值落在插件目录内。
   * 按 Discuz 惯例存放、且会随插件升级被覆盖的数据请改用 {@see pluginData()}。
   *
   * @return string|null 形如 `{project}/source/plugin/{appId}/Data`
   */
  public static function data()
  {
    return self::join(self::root(), "Data");
  }
  /**
   * 本插件的数据目录（绝对路径）
   *
   * 按 Discuz 插件数据惯例放在站点 data 目录下，即 `data/plugindata/{appId}` ——
   * 该位置不在插件目录内，插件升级 / 重装不会影响里面的文件。
   *
   * @return string|null 形如 `{project}/data/plugindata/{appId}`；应用未实例化（取不到 AppId）时为 null
   */
  public static function pluginData()
  {
    $appId = getApp()::id();
    if ($appId === null) {
      return null;
    }
    return self::join(self::project(), "data", "plugindata", $appId);
  }

  /**
   * 本插件的存储目录（绝对路径）—— 覆盖 {@see Path::storage()}
   *
   * 取 {@see pluginData()} 的 `Storage` 子目录，即把用户上传的文件放 Discuz data 下，
   * 而不是写进会被升级覆盖的插件目录。
   *
   * @return string|null 形如 `{project}/data/plugindata/{appId}/Storage`
   */
  public static function storage()
  {
    $data = self::pluginData();
    return $data === null ? null : self::join($data, "Storage");
  }

  /**
   * 应用目录（相对路径，相对项目根）—— 覆盖 {@see Path::dir()}
   *
   * 供需要把应用位置拼进 URL / 模板引用的场景使用。
   *
   * @return string|null 形如 `source/plugin/{appId}`
   */
  public static function dir()
  {
    $appRoot = self::root();
    if ($appRoot === null) {
      return null;
    }
    return self::relativePath($appRoot, (string) self::project());
  }

  /**
   * 插件目录（绝对路径）
   *
   * @return string 形如 `{project}/source/plugin`
   */
  public static function plugin(): string
  {
    return (string) self::join(self::project(), "source", "plugin");
  }

  /**
   * 插件目录（相对路径，相对项目根）
   *
   * 供需要把插件目录拼成 URL / 相对引用的场景使用。
   *
   * @return string 固定为 `source/plugin`
   */
  public static function pluginDir(): string
  {
    return "source/plugin";
  }
}
