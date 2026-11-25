<?php

namespace kernel\Foundation\FileSystem;

use kernel\Discuz\Foundation\DiscuzApp;
use kernel\Foundation\Application\Applications;

/**
 * 路径体系（纯路径计算，无副作用）
 *
 * 集中管理当前应用的目录路径推导，与文件操作解耦。全部为静态 getter，
 * 每次调用自动计算，无任何静态属性、无缓存。
 *
 * 路径 getter：
 *   kernel        = 本类文件所在目录（内核目录，永远正确，无需任何外部输入）
 *   project       = Discuz 平台取 DISCUZ_ROOT（去尾斜杠），否则为 kernel 的上级目录
 *   root          = kernel 同级目录下的 {appId}，不传取默认应用
 *   data          = Discuz 下为 {project}/data/plugindata/{appId}，否则 root/Data
 *   storage       = Discuz 下为 data/Storage，否则 root/Storage
 *   kernelDir/dir = kernel/root 相对 project 的路径
 *
 * 依赖默认应用 ID 的 getter（root/data/storage/dir）在尚未登记任何 App
 * （Applications::defaultId() 为 null）时返回 null。
 *
 * 非 final，可被继承扩展；getter 均为 public static，便于子类重写。
 */
class Path
{
  /**
   * 根目录（绝对路径）
   *
   * Discuz 下即 `DISCUZ_ROOT`（去尾斜杠）；否则为内核目录的上级目录。
   * 环境判断复用 {@see DiscuzApp::exists()}，与内核其余模块保持一致。
   *
   * @return string|null
   */
  public static function project()
  {
    if (DiscuzApp::exists()) {
      return rtrim(\DISCUZ_ROOT, "/\\");
    }
    $kernel = self::kernel();
    return $kernel === null ? null : dirname($kernel);
  }

  /**
   * 内核根目录（绝对路径）
   *
   * 即本类所在目录，与 kernelId、部署位置无关，永远正确。
   *
   * @return string
   */
  public static function kernel()
  {
    return dirname(__DIR__, 2);
  }

  /**
   * 应用根目录（绝对路径），默认 {kernel 同级目录}/{appId}
   *
   * 多应用共存时必须显式传 `$app->appId()` 才能拿到调用方自己那份（配置目录、数据目录
   * 都靠它定位）；不传则取默认应用。
   *
   * @param string|null $appId 应用 ID，不传取默认应用
   * @return string|null 应用尚未创建时返回 null
   */
  public static function root($appId = null)
  {
    $appId = $appId ?? Applications::defaultId();
    if ($appId === null || $appId === "") {
      return null;
    }

    return self::join(dirname(self::kernel()), $appId);
  }

  /**
   * 指定应用的配置目录（绝对路径），即 appRoot/Configs
   *
   * @param string|null $appId 应用 ID，不传取默认应用
   * @return string|null
   */
  public static function configs($appId = null)
  {
    $root = self::root($appId);

    return $root === null ? null : self::join($root, "Configs");
  }

  /**
   * 指定应用的数据目录（绝对路径）
   *
   * 独立部署：`{appRoot}/Data`，在应用目录内。
   * Discuz 下：`{project}/data/plugindata/{appId}` —— 站点 data 目录不在插件目录内，
   * 插件升级 / 重装不会覆盖；{@see storage()} 即它的 `Storage` 子目录。
   *
   * 与 {@see configs()} / {@see storage()} 一样支持指定应用：多应用同进程时
   * 传 `$app->appId()` 才能拿到调用方自己那份，不传永远取默认应用。
   *
   * @param string|null $appId 应用 ID，不传取默认应用
   * @return string|null
   */
  public static function data($appId = null)
  {
    $appId = $appId ?? Applications::defaultId();
    if ($appId === null || $appId === "") {
      return null;
    }

    if (DiscuzApp::exists()) {
      return self::join(self::project(), "data", "plugindata", $appId);
    }

    $appRoot = self::root($appId);
    return $appRoot === null ? null : self::join($appRoot, "Data");
  }

  /**
   * 应用存储目录（绝对路径）
   *
   * 独立部署：`{appRoot}/Storage`，随应用目录一起部署。
   * Discuz 下：`{@see data()}/Storage` —— 用户文件不能写进插件目录，
   * 那里会被插件升级 / 重装覆盖。
   *
   * @return string|null
   */
  public static function storage()
  {
    if (DiscuzApp::exists()) {
      $data = self::data();

      return $data === null ? null : self::join($data, "Storage");
    }

    $appRoot = self::root();
    return $appRoot === null ? null : self::join($appRoot, "Storage");
  }

  /**
   * 内核目录（相对路径），即 kernel 相对 project 的路径
   *
   * @return string|null
   */
  public static function kernelDir()
  {
    $project = self::project();
    $kernel = self::kernel();
    if ($project === null || $kernel === null) {
      return null;
    }
    return self::relativePath($kernel, $project);
  }

  /**
   * 应用目录（相对路径，相对项目根）
   *
   * 独立部署返回 `{appId}`；Discuz 插件布局返回 `source/plugin/{appId}`。
   * 固定用 `/` 分隔，既可拼文件系统路径（PHP 两种分隔符都认），也可直接拼 URL。
   *
   * @param string|null $appId 应用 ID，不传取默认应用
   * @return string|null
   */
  public static function dir($appId = null)
  {
    $appRoot = self::root($appId);
    $project = self::project();
    if ($appRoot === null || $project === null) {
      return null;
    }

    return str_replace("\\", "/", self::relativePath($appRoot, $project));
  }

  /**
   * 求 $path 相对 $base（绝对路径前缀）的路径
   *
   * @param string $path 绝对路径
   * @param string $base 绝对路径前缀
   * @return string
   */
  protected static function relativePath(string $path, string $base): string
  {
    $relative = substr($path, strlen($base));
    return ltrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
  }
  /**
   * 组合多个路径段为一个完整路径
   *
   * 自动过滤空路径段，并规范化路径分隔符为当前系统的 DIRECTORY_SEPARATOR。
   * 适用于跨平台路径拼接。
   *
   * 使用示例：
   * ```php
   * self::join('/var/www', 'app', 'config.php');
   * // Linux:   "/var/www/app/config.php"
   * // Windows: "\var\www\app\config.php"
   * ```
   *
   * @param string ...$paths 可变数量的路径段
   * @return string 组合后的完整路径
   */
  public static function join(...$paths)
  {
    $paths = array_filter($paths, function ($item) {
      return !($item === null || $item === "");
    });
    $path = implode(DIRECTORY_SEPARATOR, $paths);
    $path = str_replace([
      "//",
      "\\",
      "/",
      "\\\\"
    ], DIRECTORY_SEPARATOR, $path);

    return $path;
  }

  /**
   * 优化路径分隔符
   *
   * 将路径中的 "/" 与 "\" 统一替换为当前运行系统的 DIRECTORY_SEPARATOR。
   * 已从 FileHelper 迁移至此，作为路径规范化的统一入口。
   *
   * 使用示例：
   * ```php
   * Path::optimizedPath('path/to\\file.txt');
   * // Linux:   "path/to/file.txt"
   * // Windows: "path\to\file.txt"
   * ```
   *
   * @param string $path 需要优化的路径字符串
   * @return string 优化后的路径
   */
  public static function optimizedPath($path)
  {
    return str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
  }
  /**
   * 规范化路径（纯字符串计算，不访问文件系统）
   *
   * 消解路径中的 `.` 与 `..` 段：`.` 丢弃、`..` 弹出上一段（栈空时忽略），
   * 折叠重复分隔符并统一为 `/`。与 {@see realpath()} 的区别：realpath 要求
   * 目标已存在（上传落盘的是新文件，会返回 false），本方法只做字符串计算，
   * 适合在落盘前求出「真实目标位置」用于校验与回显。
   *
   * 使用示例：
   * ```php
   * Path::canonicalize('/data/uploads/../attachments/a.jpg');
   * // "/data/attachments/a.jpg"
   * Path::canonicalize('data/plugindata/./x');
   * // "data/plugindata/x"
   * ```
   *
   * @param string $path 原始路径
   * @return string 规范化后的路径
   */
  public static function canonicalize($path)
  {
    $path = str_replace("\\", "/", (string) $path);
    $absolute = strpos($path, "/") === 0;
    $segments = [];
    foreach (explode("/", $path) as $segment) {
      if ($segment === "" || $segment === ".") {
        continue;
      }
      if ($segment === "..") {
        array_pop($segments);
        continue;
      }
      array_push($segments, $segment);
    }
    return ($absolute ? "/" : "") . implode("/", $segments);
  }
}
