<?php

namespace kernel\Foundation\File;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FileHelper
{
  /**
   * 判断指定的文件是不是视频文件，该文件必须存在
   *
   * @param string $fileName 文件路径(包含文件名和扩展名) root/path/show.mp4名
   * @return boolean 是视频返回true，否则返回false
   */
  public static function isVideo($fileName)
  {
    $Mine = mime_content_type($fileName);
    if (!$Mine) return false;
    return explode("/", $Mine)[0] === "video";
  }
  /**
   * 判断指定的文件是否是图片文件，该文件必须存在
   *
   * @param string $fileName 文件路径(包含文件名和扩展名) root/path/image.png
   * @return boolean 是图片返回true，否则返回false
   */
  public static function isImage($fileName)
  {
    $Mine = mime_content_type($fileName);
    if (!$Mine) return false;
    return explode("/", $Mine)[0] === "image";
  }
  /**
   * 组合成一个文件路径
   *
   * @param string ...$els 路径项
   * @return string 生成后的路径
   */
  public static function combinedFilePath(...$paths)
  {
    $path = implode(DIRECTORY_SEPARATOR, array_map(function ($item) {
      // $lastText = $item[strlen($item) - 1];
      // if ($lastText === "/" || $lastText === "\\") {
      //   $item = substr($item, 0, strlen($item) - 1);
      // }
      // if ($item[0] === "/" || $item[0] === "\\") {
      //   $item = substr($item, 1, strlen($item));
      // }
      return $item;
    }, array_filter($paths, function ($item) {
      return !empty(trim($item));
    })));
    $path = str_replace([
      "//",
      "\\",
      "/",
      "\\\\"
    ], DIRECTORY_SEPARATOR, $path);

    return $path;
  }
  /**
   * 优化文件路径。修改路径中分隔符与当前运行系统的分隔符一直
   *
   * @param string $path
   * @return string 优化后的文件路径
   */
  static function optimizedPath($path)
  {
    return str_replace([
      "/",
      "\\"
    ], DIRECTORY_SEPARATOR, $path);
  }
  /**
   * 扫描目录
   *
   * @param string $targetPath 被扫描的目录路径
   * @param integer|null $sorting_order 默认的排序顺序是按字母升序排列。如果使用了可选参数 sorting_order（设为 1），则排序顺序是按字母降序排列。
   * @param mixed $context 参数的说明见手册中的 Streams API(https://www.php.net/manual/zh/ref.stream.php) 一章。
   * @return array|false 扫描成功的话就返回扫描的数组，否则返回false
   */
  public static function scandir($targetPath, $sorting_order = 0, $context = null)
  {
    if ($context !== null) {
      $dirs = scandir($targetPath, $sorting_order, $context);
    } else {
      $dirs = scandir($targetPath, $sorting_order);
    }
    if (!$dirs) return false;
    return array_values(array_filter($dirs, function ($item) {
      return !in_array($item, [".", ".."]);
    }));
  }
  /**
   * 比较两个目录是否相等
   * 会扫描两个目录深度比较
   *
   * @param string $targetPath 目录1
   * @param string $sourcePath 目录2
   * @return boolean 是否相等
   */
  public static function compareDirectories($targetPath, $sourcePath)
  {
    //* 如果任意一个路径是文件夹，而另外一个是文件，就返回false
    if (!is_dir($targetPath) && is_dir($sourcePath) || !is_dir($sourcePath) && is_dir($targetPath)) {
      return false;
    }

    $targetFiles = self::scandir($targetPath);
    $sourceFiles = self::scandir($sourcePath);
    if (count($targetFiles) !== count($sourceFiles)) {
      return false;
    }

    $result = true;
    foreach ($targetFiles as $index => $targetFileItem) {
      if (is_dir($targetFileItem)) {
        if (!self::compareDirectories(self::combinedFilePath($targetPath, $targetFileItem),  self::combinedFilePath($targetPath, $sourceFiles[$index]))) {
          $result = false;
          break;
        }
      } else {
        if ($targetFileItem !== $sourceFiles[$index]) {
          $result = false;
          break;
        }
      }
    }

    return $result;
  }
  /**
   * 递归扫描目标文件夹
   *
   * @param string $rootDir 被扫描的目标文件夹路径
   * @param string|boolean $parentDir 包含文件夹路径
   * @param boolean $includeRootDir 包含根文件夹路径
   * @return string[] 扫描后的文件列表，没有分层
   */
  public static function recursionScanDir($rootDir, $parentDir = null, $includeRootDir = false)
  {
    if (!is_dir($rootDir)) return [];
    $dirs = self::scandir($rootDir);
    $allDirs = [];
    foreach ($dirs as $dir) {
      if (is_dir(self::combinedFilePath($rootDir, $dir))) {
        $allDirs = array_merge($allDirs, self::recursionScanDir(self::combinedFilePath($rootDir, $dir), is_null($parentDir) || $parentDir === false ? false : $dir, $includeRootDir));
        if ($includeRootDir) {
          array_push($allDirs, self::combinedFilePath($rootDir, $dir));
        } else if ($parentDir !== false && !is_null($parentDir)) {
          array_push($allDirs, is_bool($parentDir) ?  $dir : self::combinedFilePath($parentDir, $dir));
        } else {
          array_push($allDirs, $dir);
        }
      } else {
        if ($includeRootDir) {
          array_push($allDirs, self::combinedFilePath($rootDir, $dir));
        } else if ($parentDir !== false && !is_null($parentDir)) {
          array_push($allDirs, is_bool($parentDir) ?  $dir : self::combinedFilePath($parentDir, $dir));
        } else {
          array_push($allDirs, $dir);
        }
      }
    }
    return $allDirs;
  }
}
