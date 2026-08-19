<?php

namespace kernel\Foundation\FileSystem;


/**
 * 文件操作辅助类
 *
 * 提供文件类型判断、路径处理、目录扫描、格式化等底层工具方法。
 * 所有方法均为静态方法，无需实例化。
 *
 * @package kernel\Foundation\FileSystem
 */
class FileHelper
{
  /**
   * 判断指定的文件是否是视频文件
   *
   * 通过 MIME 类型判断，该文件必须存在且可读。
   *
   * 使用示例：
   * ```php
   * if (FileHelper::isVideo('/path/to/video.mp4')) {
   *     // 处理视频文件
   * }
   * ```
   *
   * @param string $fileName 文件完整路径（包含文件名和扩展名）
   * @return boolean 是视频返回 true，文件不存在或非视频返回 false
   */
  public static function isVideo($fileName)
  {
    $mime = mime_content_type($fileName);
    if ($mime === false) return false;
    return explode("/", $mime)[0] === "video";
  }
  /**
   * 判断指定的文件是否是图片文件
   *
   * 通过 MIME 类型判断，该文件必须存在且可读。
   *
   * 使用示例：
   * ```php
   * if (FileHelper::isImage('/path/to/photo.png')) {
   *     // 处理图片文件
   * }
   * ```
   *
   * @param string $fileName 文件完整路径（包含文件名和扩展名）
   * @return boolean 是图片返回 true，文件不存在或非图片返回 false
   */
  public static function isImage($fileName)
  {
    $mime = mime_content_type($fileName);
    if ($mime === false) return false;
    return explode("/", $mime)[0] === "image";
  }
  /**
   * 判断指定的文件是否是音频文件
   *
   * 通过 MIME 类型判断，该文件必须存在且可读。
   *
   * 使用示例：
   * ```php
   * if (FileHelper::isAudio('/path/to/music.mp3')) {
   *     // 处理音频文件
   * }
   * ```
   *
   * @param string $filePath 文件完整路径（包含文件名和扩展名）
   * @return boolean 是音频返回 true，文件不存在或非音频返回 false
   */
  static function isAudio($filePath)
  {
    $mime = mime_content_type($filePath);
    if ($mime === false) return false;
    return explode("/", $mime)[0] === "audio";
  }
  /**
   * 获取文件的 MIME 类型
   *
   * 通过 PHP 内置的 mime_content_type 获取文件 MIME 类型。
   * 在进行文件类型判断时，推荐使用 isImage()、isVideo()、isAudio() 等方法。
   *
   * 使用示例：
   * ```php
   * $mime = FileHelper::getMimeType('/path/to/file.pdf');
   * // 返回: "application/pdf"
   * ```
   *
   * @param string $filePath 文件完整路径
   * @return string|false MIME 类型字符串（如 "image/png"），文件不存在或读取失败时返回 false
   * @see FileHelper::isImage()
   * @see FileHelper::isVideo()
   * @see FileHelper::isAudio()
   */
  static function getMimeType($filePath)
  {
    if (!file_exists($filePath)) {
      return false;
    }
    return mime_content_type($filePath);
  }
  /**
   * 获取文件扩展名（不含点号）
   *
   * 使用示例：
   * ```php
   * FileHelper::extension('/path/to/file.txt');   // "txt"
   * FileHelper::extension('archive.tar.gz');      // "gz"
   * FileHelper::extension('noextension');         // ""
   * ```
   *
   * @param string $path 文件路径或文件名
   * @return string 扩展名字符串（不含前导点号），无扩展名时返回空字符串
   */
  static function extension($path)
  {
    return pathinfo($path, PATHINFO_EXTENSION);
  }
  /**
   * 组合多个路径段为一个完整路径
   *
   * 自动过滤空路径段，并规范化路径分隔符为当前系统的 DIRECTORY_SEPARATOR。
   * 适用于跨平台路径拼接。
   *
   * 使用示例：
   * ```php
   * FileHelper::combinedFilePath('/var/www', 'app', 'config.php');
   * // Linux:   "/var/www/app/config.php"
   * // Windows: "\var\www\app\config.php"
   * ```
   *
   * @param string ...$paths 可变数量的路径段
   * @return string 组合后的完整路径
   */
  public static function combinedFilePath(...$paths)
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
   * 优化文件路径
   *
   * 将路径中的分隔符统一替换为当前运行系统的 DIRECTORY_SEPARATOR。
   *
   * 使用示例：
   * ```php
   * FileHelper::optimizedPath('path/to\\file.txt');
   * // Linux:   "path/to/file.txt"
   * // Windows: "path\to\file.txt"
   * ```
   *
   * @param string $path 需要优化的路径字符串
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
   * 对 PHP 内置 scandir() 的增强封装，自动过滤掉 "." 和 ".."，
   * 并使用 array_values 重新索引结果数组。
   *
   * 使用示例：
   * ```php
   * $files = FileHelper::scandir('/path/to/directory');
   * // 返回: ['file1.txt', 'file2.txt', 'subdir']  不包含 "." 和 ".."
   *
   * // 降序排列
   * $files = FileHelper::scandir('/path/to/directory', 1);
   * ```
   *
   * @param string $targetPath 被扫描的目录路径
   * @param integer $sortingOrder 排序方式。0=升序（默认），1=降序
   * @param mixed|null $context 流上下文资源，参见 PHP Streams API 文档
   * @return array|false 扫描成功的文件名数组（不含 "." 和 ".."），失败返回 false
   */
  public static function scandir($targetPath, $sortingOrder = 0, $context = null)
  {
    if ($context !== null) {
      $dirs = scandir($targetPath, $sortingOrder, $context);
    } else {
      $dirs = scandir($targetPath, $sortingOrder);
    }
    if ($dirs === false) return false;
    return array_values(array_filter($dirs, function ($item) {
      return !in_array($item, [".", ".."]);
    }));
  }
  /**
   * 递归扫描目录
   *
   * 深度遍历目标目录及其所有子目录，返回所有文件的路径列表。
   * 支持返回绝对路径或相对路径。
   *
   * 使用示例：
   * ```php
   * // 获取所有文件的相对路径
   * $files = FileHelper::recursionScanDir('/path/to/project');
   * // 返回: ['src/App.php', 'src/Config.php', 'public/index.php', ...]
   *
   * // 获取所有文件的绝对路径
   * $files = FileHelper::recursionScanDir('/path/to/project', null, true);
   * // 返回: ['/path/to/project/src/App.php', ...]
   *
   * // 指定父级路径前缀
   * $files = FileHelper::recursionScanDir('/path/to/project/src', 'project');
   * // 返回: ['project/App.php', 'project/Config.php', ...]
   * ```
   *
   * @param string $rootDir 被扫描的根目录路径
   * @param string|null $parentDir 父级路径前缀。传入字符串时会作为路径前缀添加到结果中，传入 null 或 false 时仅使用文件自身名称作为路径
   * @param boolean $includeRootDir 是否在结果中包含根目录路径。true=返回绝对路径，false=返回相对路径或仅文件名
   * @return string[] 扫描后的文件路径列表（一维数组）
   */
  public static function recursionScanDir($rootDir, $parentDir = null, $includeRootDir = false)
  {
    if (!is_dir($rootDir)) return [];
    $dirs = self::scandir($rootDir);
    if ($dirs === false) return [];

    $allDirs = [];
    foreach ($dirs as $dir) {
      $fullPath = self::combinedFilePath($rootDir, $dir);
      $relativePath = $parentDir === null || $parentDir === false
        ? $dir
        : self::combinedFilePath($parentDir, $dir);

      if (is_dir($fullPath)) {
        $allDirs = array_merge(
          $allDirs,
          self::recursionScanDir($fullPath, $relativePath, $includeRootDir)
        );
      }

      if ($includeRootDir) {
        $allDirs[] = $fullPath;
      } else {
        $allDirs[] = $relativePath;
      }
    }
    return $allDirs;
  }
  /**
   * 深度比较两个目录是否相等
   *
   * 递归扫描两个目录，比较目录结构（文件/子目录名称）是否完全相同。
   * 注意：此方法仅比较文件名称，不比较文件内容。
   *
   * 使用示例：
   * ```php
   * // 比较两个版本的模板目录结构
   * $equal = FileHelper::compareDirectories('/templates/v1', '/templates/v2');
   * ```
   *
   * @param string $targetPath 第一个目录路径
   * @param string $sourcePath 第二个目录路径
   * @return boolean 两个目录结构完全相同时返回 true
   */
  public static function compareDirectories($targetPath, $sourcePath)
  {
    // 如果任意一个路径是文件夹，而另外一个是文件，就返回false
    if ((!is_dir($targetPath) && is_dir($sourcePath)) || (!is_dir($sourcePath) && is_dir($targetPath))) {
      return false;
    }
    // 两者都是文件
    if (!is_dir($targetPath) && !is_dir($sourcePath)) {
      return $targetPath === $sourcePath;
    }

    $targetFiles = self::scandir($targetPath);
    $sourceFiles = self::scandir($sourcePath);
    if ($targetFiles === false || $sourceFiles === false) return false;
    if (count($targetFiles) !== count($sourceFiles)) {
      return false;
    }

    $result = true;
    foreach ($targetFiles as $index => $targetFileItem) {
      $targetFullPath = self::combinedFilePath($targetPath, $targetFileItem);
      $sourceFullPath = self::combinedFilePath($sourcePath, $sourceFiles[$index]);
      if (is_dir($targetFullPath)) {
        if (!self::compareDirectories($targetFullPath, $sourceFullPath)) {
          $result = false;
          break;
        }
      } else {
        if (!is_dir($sourceFullPath) && $targetFileItem !== $sourceFiles[$index]) {
          $result = false;
          break;
        }
      }
    }

    return $result;
  }
  /**
   * 将字节数格式化为人类可读的大小字符串
   *
   * 自动选择合适的单位（B/KB/MB/GB/TB/PB）进行格式化。
   *
   * 使用示例：
   * ```php
   * FileHelper::humanReadableSize(0);           // "0 B"
   * FileHelper::humanReadableSize(1024);        // "1 KB"
   * FileHelper::humanReadableSize(1536000);     // "1.46 MB"
   * FileHelper::humanReadableSize(1536000, 0);  // "1 MB"
   * ```
   *
   * @param integer $bytes 字节数
   * @param integer $decimals 小数位数，默认 2
   * @return string 格式化后的大小字符串，如 "1.46 MB"
   */
  static function humanReadableSize($bytes, $decimals = 2)
  {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $factor = floor((strlen((string)$bytes) - 1) / 3);
    if ($factor < 0) $factor = 0;
    $value = $bytes / pow(1024, $factor);
    return round($value, $decimals) . ' ' . $units[$factor];
  }
  /**
   * 获取 PHP 配置允许的最大上传文件大小
   *
   * 取 php.ini 中 post_max_size 和 upload_max_filesize 的较小值作为结果。
   * 可用于上传前的文件大小校验。
   *
   * 使用示例：
   * ```php
   * $maxSize = FileHelper::maxUploadSize();
   * if ($fileSize > $maxSize) {
   *     throw new Exception('文件大小超出限制: ' . FileHelper::humanReadableSize($maxSize));
   * }
   * ```
   *
   * @return integer 最大上传大小（字节）
   */
  static function maxUploadSize()
  {
    $postMaxSize = self::parseIniSize(ini_get('post_max_size'));
    $uploadMaxSize = self::parseIniSize(ini_get('upload_max_filesize'));
    return min($postMaxSize, $uploadMaxSize);
  }
  /**
   * 解析 PHP ini 大小字符串为字节数
   *
   * 将 "2M"、"1G"、"512K" 等 PHP ini 格式的大小字符串转换为整数字节数。
   * 此为内部辅助方法。
   *
   * @param string $size ini 大小字符串，如 "2M"、"1G"、"512K"
   * @return integer 转换后的字节数
   * @internal
   */
  private static function parseIniSize($size)
  {
    $unit = strtoupper(substr($size, -1));
    $value = (int)$size;
    switch ($unit) {
      case 'G':
        $value *= 1024;
        // no break
      case 'M':
        $value *= 1024;
        // no break
      case 'K':
        $value *= 1024;
    }
    return $value;
  }
}
