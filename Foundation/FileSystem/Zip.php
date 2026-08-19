<?php

namespace kernel\Foundation\FileSystem;

use ZipArchive;

/**
 * ZIP 压缩/解压工具
 *
 * 基于 PHP 内置 ZipArchive 的目录打包与解压封装：
 * - zipDirectory() / pack()：将目录递归打包为 zip，支持黑名单排除、压缩等级配置；
 * - unzip() / extract()：解压 zip 到指定目录，内置 zip slip（路径穿越）防护、
 *   解压大小/文件数上限（防 zip bomb）、符号链接策略；
 * - isZip()：快速校验文件是否为合法 zip。
 *
 * 黑名单规则（blacklist，默认 [".git", "README.md"]）：
 * - 普通条目按文件名精确匹配（任意层级同名即排除，如 .git、README.md）；
 * - 含通配符（*、?、[]）的条目按 fnmatch 对文件名匹配（如 *.md）。
 *
 * 同时提供实例链式调用（setCompressionLevel/exclude）与静态无状态入口
 * （pack/extract，选项数组传入，不依赖实例状态）。
 */
class Zip
{
  /**
   * 打包排除名单：普通条目精确匹配文件名，含通配符的条目 fnmatch 匹配
   *
   * @var array
   */
  public $blacklist = [
    ".git",
    "README.md"
  ];
  /**
   * 压缩等级：-1 使用 ZipArchive 默认，0-9 为 DEFLATE 压缩等级
   *
   * @var integer
   */
  public $compressionLevel = -1;
  /**
   * 解压后总大小上限（字节），0 表示不限制（防 zip bomb）
   *
   * @var integer
   */
  public $maxExtractSize = 0;
  /**
   * 解压文件数上限，0 表示不限制（防 zip bomb）
   *
   * @var integer
   */
  public $maxExtractFiles = 0;
  /**
   * 解压时是否保留符号链接条目：false 时跳过符号链接（防止链接指向目标目录外）
   *
   * @var boolean
   */
  public $preserveSymlinks = false;
  /**
   * 精确匹配的黑名单文件名（每次打包前重置，仅对本次打包生效）
   *
   * @var array
   */
  protected $blacklistFileNames = [];
  /**
   * 通配符黑名单（fnmatch 模式，仅对本次打包生效）
   *
   * @var array
   */
  protected $blacklistWildcards = [];
  /**
   * 最近一次操作的错误信息（成功或未操作时为 null）
   *
   * @var string|null
   */
  protected $lastError = null;

  /**
   * 配置压缩等级（链式）
   *
   * @param integer $level -1 使用默认，0-9 为 DEFLATE 压缩等级
   * @return $this
   */
  public function setCompressionLevel($level)
  {
    $level = (int)$level;
    if ($level < -1 || $level > 9) {
      $this->lastError = "压缩等级必须是 -1 到 9，收到：{$level}";
      return $this;
    }
    $this->compressionLevel = $level;

    return $this;
  }

  /**
   * 追加黑名单条目（链式，去重合并进 blacklist）
   *
   * @param array $names 文件名或 fnmatch 通配符（如 [".svn", "*.log"]）
   * @return $this
   */
  public function exclude(array $names)
  {
    $this->blacklist = array_values(array_unique(
      array_merge($this->blacklist, $names)
    ));

    return $this;
  }

  /**
   * 获取最近一次操作的错误信息
   *
   * @return string|null 失败原因描述；成功或未操作为 null
   */
  public function lastError()
  {
    return $this->lastError;
  }

  /**
   * 快速校验文件是否为合法 zip（magic bytes）
   *
   * @param string $path 待校验文件路径
   * @return boolean 是 zip 返回 true，文件不存在或 magic 不符返回 false
   */
  public function isZip($path)
  {
    if (!is_file($path)) {
      return false;
    }
    $fp = @fopen($path, "rb");
    if ($fp === false) {
      return false;
    }
    $magic = fread($fp, 4);
    fclose($fp);

    return in_array($magic, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"]);
  }

  /**
   * 将目录递归打包为 zip
   *
   * 目录不存在返回 false；打包过程中黑名单内条目（任意层级）会被跳过。
   * 失败原因可通过 lastError() 读取。
   *
   * @param string $sourcePath 源目录路径
   * @param string $outputPath 输出 zip 文件路径（已存在则覆盖）
   * @return boolean 打包成功返回 true，源目录不存在或打开 zip 失败返回 false
   */
  public function zipDirectory($sourcePath, $outputPath)
  {
    $sourcePath = rtrim(FileHelper::combinedFilePath($sourcePath), "/");
    if (!is_dir($sourcePath)) {
      $this->lastError = "源目录不存在：{$sourcePath}";
      return false;
    }

    $zip = new ZipArchive();
    $result = file_exists($outputPath)
      ? $zip->open($outputPath, ZipArchive::OVERWRITE)
      : $zip->open($outputPath, ZipArchive::CREATE);
    if ($result !== true) {
      $this->lastError = "无法打开输出 zip 文件：{$outputPath}";
      return false;
    }

    //* 黑名单只对本次打包生效（连续打包多个目录时互不残留）
    $this->blacklistFileNames = [];
    $this->blacklistWildcards = [];
    foreach ($this->blacklist as $item) {
      if (strpbrk($item, "*?[") !== false) {
        array_push($this->blacklistWildcards, $item);
      } else {
        array_push($this->blacklistFileNames, $item);
      }
    }

    $this->lastError = null;
    $this->directoryToZip($zip, $sourcePath, strlen($sourcePath));

    $zip->close();

    return true;
  }

  /**
   * 解压 zip 到指定目录
   *
   * 目标目录不存在会自动创建（0755）。内置多重防护：
   * - zip slip：含 ".."、绝对路径（/ 或盘符开头）的条目直接拒绝；
   * - 目标目录越界：解压目标 realpath 必须在 $dest 之内；
   * - zip bomb：解压总大小 / 文件数超过 maxExtractSize / maxExtractFiles 上限即中止；
   * - 符号链接：preserveSymlinks 为 false（默认）时跳过链接条目。
   * 失败原因可通过 lastError() 读取。
   *
   * @param string $filePath zip 文件路径
   * @param string $dest 解压目标目录
   * @return boolean 解压成功返回 true，zip 无效、含危险条目或超限返回 false
   */
  public function unzip(string $filePath, string $dest)
  {
    if (!is_file($filePath)) {
      $this->lastError = "zip 文件不存在：{$filePath}";
      return false;
    }
    if (!is_dir($dest)) {
      if (!mkdir($dest, 0755, true)) {
        $this->lastError = "无法创建解压目录：{$dest}";
        return false;
      }
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
      $this->lastError = "无法打开 zip 文件（可能已损坏）：{$filePath}";
      return false;
    }

    $ok = $this->extractWithGuard($zip, $dest);
    $zip->close();

    return $ok;
  }

  /**
   * 静态快捷打包：将目录递归打包为 zip（无状态入口）
   *
   * @param string $sourcePath 源目录路径
   * @param string $outputPath 输出 zip 文件路径
   * @param array $options 选项：blacklist（追加黑名单）、compressionLevel（压缩等级 -1~9）
   * @return boolean 打包成功返回 true
   */
  public static function pack($sourcePath, $outputPath, array $options = [])
  {
    $zip = new self();
    if (isset($options["blacklist"])) {
      $zip->exclude((array)$options["blacklist"]);
    }
    if (isset($options["compressionLevel"])) {
      $zip->setCompressionLevel($options["compressionLevel"]);
    }

    return $zip->zipDirectory($sourcePath, $outputPath);
  }

  /**
   * 静态快捷解压：解压 zip 到指定目录（无状态入口）
   *
   * @param string $filePath zip 文件路径
   * @param string $dest 解压目标目录
   * @param array $options 选项：maxExtractSize（解压总大小上限，0 不限）、
   *   maxExtractFiles（文件数上限，0 不限）、preserveSymlinks（是否保留符号链接）
   * @return boolean 解压成功返回 true
   */
  public static function extract($filePath, $dest, array $options = [])
  {
    $zip = new self();
    foreach (["maxExtractSize", "maxExtractFiles", "preserveSymlinks"] as $key) {
      if (isset($options[$key])) {
        $zip->{$key} = $options[$key];
      }
    }

    return $zip->unzip($filePath, $dest);
  }

  /**
   * 守卫式解压（内部辅助）
   *
   * 逐条目流式解压并累计实际写入字节数：校验条目路径安全（zip slip）、
   * 校验目标目录在 $dest 之内（realpath 前缀）、统计文件数/总大小并
   * 对照 maxExtractFiles / maxExtractSize 上限，超限即中止并返回 false。
   * 目录条目创建目录；符号链接条目按 preserveSymlinks 决定保留或跳过。
   *
   * @param \ZipArchive $zip 已打开的 zip 实例
   * @param string $dest 解压目标目录
   * @return boolean 全部安全解压返回 true，任一校验失败或写入失败返回 false
   */
  private function extractWithGuard($zip, $dest)
  {
    $realDest = realpath($dest);
    $extractedSize = 0;
    $fileCount = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
      $stat = $zip->statIndex($i);
      $name = $stat["name"] ?? null;
      if ($name === null) {
        continue;
      }

      //* zip slip 防护：拒绝路径穿越与绝对路径条目
      if (str_contains($name, "..") || str_starts_with($name, "/") || preg_match('#^[A-Za-z]:[/\\\\]#', $name)) {
        $this->lastError = "zip 含不安全条目，已拒绝：{$name}";
        return false;
      }

      $destPath = FileHelper::combinedFilePath($dest, $name);
      //* 目标目录越界防护：条目解析出的真实目录必须在解压目标之内
      $realDir = realpath(dirname($destPath));
      if ($realDir === false || ($realDir !== $realDest && !str_starts_with($realDir, $realDest . "/"))) {
        $this->lastError = "条目目标超出解压目录，已拒绝：{$name}";
        return false;
      }

      //* 目录条目
      if (str_ends_with($name, "/") || ($stat["crc"] === 0 && !$stat["size"])) {
        if (!is_dir($destPath)) {
          mkdir($destPath, 0755, true);
        }
        continue;
      }

      //* 文件数上限（zip bomb 防护）
      $fileCount++;
      if ($this->maxExtractFiles > 0 && $fileCount > $this->maxExtractFiles) {
        $this->lastError = "解压文件数超过上限 {$this->maxExtractFiles}，已中止";
        return false;
      }

      //* 符号链接条目（unix mode：S_IFLNK = 0xA000；部分 zip 无 external 元数据）
      $isSymlink = (($stat["external"] ?? 0) & 0xF000) === 0xA000;
      if ($isSymlink) {
        if (!$this->preserveSymlinks) {
          continue;
        }
        $target = $zip->getFromIndex($i);
        if ($target === false || !@symlink($target, $destPath)) {
          $this->lastError = "无法创建符号链接：{$name}";
          return false;
        }
        continue;
      }

      //* 解压总大小上限（zip bomb 防护，按实际写入量累计）
      if ($this->maxExtractSize > 0 && $extractedSize + $stat["size"] > $this->maxExtractSize) {
        $this->lastError = "解压总大小超过上限 {$this->maxExtractSize} 字节，已中止";
        return false;
      }

      //* 流式解压
      $stream = $zip->getStream($name);
      $fh = @fopen($destPath, "wb");
      if ($stream === false || $fh === false) {
        if (is_resource($stream)) {
          fclose($stream);
        }
        $this->lastError = "无法写入解压文件：{$destPath}";
        return false;
      }
      $written = 0;
      while (!feof($stream)) {
        $chunk = fread($stream, 65536);
        if ($chunk === false) {
          break;
        }
        $written += strlen($chunk);
        fwrite($fh, $chunk);
      }
      fclose($fh);
      fclose($stream);

      $extractedSize += $written;
    }

    $this->lastError = null;

    return true;
  }

  /**
   * 递归遍历目录并写入 zip（内部辅助）
   *
   * 文件条目 addFile、目录条目 addEmptyDir 后递归；符号链接跳过，
   * 黑名单按文件名匹配任意层级；压缩等级非默认时逐条目设置。
   *
   * @param \ZipArchive $zip 目标 zip 实例
   * @param string $directory 当前遍历目录
   * @param integer $removedLength 源目录字符串长度（用于计算 zip 内相对路径）
   * @return void
   */
  private function directoryToZip($zip, $directory, $removedLength)
  {
    $dirs = FileHelper::scandir($directory);
    if ($dirs === false) {
      $this->lastError = "无法读取目录：{$directory}";
      return;
    }
    foreach ($dirs as $dirItem) {
      $sourceFilePath = FileHelper::combinedFilePath($directory, $dirItem);

      //* 符号链接不打包，避免链接目标被当作文件/目录错误处理
      if (is_link($sourceFilePath)) {
        continue;
      }
      //* 精确文件名黑名单（任意层级同名条目，如 .git、README.md）
      if (in_array($dirItem, $this->blacklistFileNames)) {
        continue;
      }
      //* 通配符黑名单（如 *.md：fnmatch 对文件名匹配）
      $skip = false;
      foreach ($this->blacklistWildcards as $item) {
        if (fnmatch($item, $dirItem)) {
          $skip = true;
          break;
        }
      }
      if ($skip) {
        continue;
      }

      $localPath = substr($sourceFilePath, $removedLength + 1);
      if (is_file($sourceFilePath)) {
        $zip->addFile($sourceFilePath, $localPath);
        if ($this->compressionLevel >= 0) {
          $zip->setCompressionName($localPath, ZipArchive::CM_DEFLATE, $this->compressionLevel);
        }
      } else {
        $zip->addEmptyDir($localPath);
        $this->directoryToZip($zip, $sourceFilePath, $removedLength);
      }
    }
  }
}
