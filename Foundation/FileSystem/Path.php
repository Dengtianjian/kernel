<?php

namespace kernel\Foundation\FileSystem;

use kernel\Foundation\App;

/**
 * 路径体系（纯路径计算，无副作用）
 *
 * 集中管理当前应用的目录路径推导，与文件操作解耦。全部为静态 getter，
 * 每次调用自动计算，无任何静态属性、无缓存。
 *
 * 7 个路径 getter：
 *   kernelRoot  = 本类文件所在目录（内核目录，永远正确，无需任何外部输入）
 *   projectRoot = DiscuzX 平台取 DISCUZ_ROOT（去尾斜杠），否则为 kernelRoot 的上级目录
 *   root        = kernelRoot 同级目录下的 {App::id()}
 *   data        = root/Data
 *   storage     = root/Storage
 *   kernelDir/dir = kernelRoot/root 相对 projectRoot 的路径
 *
 * 依赖 App::id() 的 getter（root/data/storage/dir）在未实例化 App（App::id() 为 null）
 * 时返回 null。
 *
 * 非 final，可被继承扩展；getter 均为 public static，便于子类重写。
 */
class Path
{
  /**
   * 根目录（绝对路径）
   *
   * DiscuzX 平台返回 DISCUZ_ROOT（去尾斜杠）；普通项目返回内核目录的上级目录。
   *
   * @return string|null
   */
  public static function projectRoot(): ?string
  {
    if (defined("\\DISCUZ_ROOT")) {
      return rtrim(\DISCUZ_ROOT, "/\\");
    }
    $kernelRoot = self::kernelRoot();
    return $kernelRoot === null ? null : dirname($kernelRoot);
  }

  /**
   * 内核根目录（绝对路径）
   *
   * 即本类所在目录，与 App::kernelId()、部署位置无关，永远正确。
   *
   * @return string
   */
  public static function kernelRoot(): ?string
  {
    return dirname(__DIR__, 2);
  }

  /**
   * 当前应用根目录（绝对路径），默认 {kernelRoot 同级目录}/{App::id()}
   *
   * @return string|null
   */
  public static function root(): ?string
  {
    if (App::id() === null) {
      return null;
    }
    return FileHelper::combinedFilePath(dirname(self::kernelRoot()), App::id());
  }

  /**
   * 应用数据目录（绝对路径），默认 appRoot/Data
   *
   * @return string|null
   */
  public static function data(): ?string
  {
    $appRoot = self::root();
    return $appRoot === null ? null : FileHelper::combinedFilePath($appRoot, "Data");
  }

  /**
   * 应用存储目录（绝对路径），默认 appRoot/Storage
   *
   * @return string|null
   */
  public static function storage(): ?string
  {
    $appRoot = self::root();
    return $appRoot === null ? null : FileHelper::combinedFilePath($appRoot, "Storage");
  }

  /**
   * 内核目录（相对路径），即 kernelRoot 相对 root 的路径
   *
   * @return string|null
   */
  public static function kernelDir(): ?string
  {
    $root = self::root();
    $kernelRoot = self::kernelRoot();
    if ($root === null || $kernelRoot === null) {
      return null;
    }
    return self::relativePath($kernelRoot, $root);
  }

  /**
   * 应用目录（相对路径），即 appRoot 相对 root 的路径
   *
   * @return string|null
   */
  public static function dir(): ?string
  {
    $root = self::root();
    $appRoot = self::root();
    if ($root === null || $appRoot === null) {
      return null;
    }
    return self::relativePath($appRoot, $root);
  }

  /**
   * 求 $path 相对 $base（绝对路径前缀）的路径
   *
   * @param string $path 绝对路径
   * @param string $base 绝对路径前缀
   * @return string
   */
  private static function relativePath(string $path, string $base): string
  {
    $relative = substr($path, strlen($base));
    return ltrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
  }
}
