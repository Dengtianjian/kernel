<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Exception;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

/**
 * 文件操作类
 * @deprecated
 */
class File
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
   * 上传文件，并且保存在服务器
   *
   * @param array|string $files 文件或者多个文件数组
   * @param string $savePath 保存的完整路径
   * @param string $fileName 文件名称，不含扩展名
   * @return array
   */
  public static function upload($files, $savePath, $fileName = null)
  {
    if (!$files || is_array($files) && empty($files)) return false;
    $uploadResult = [];
    $onlyOne = false;
    if (is_array($files) && Arr::isAssoc($files) || is_string($files)) {
      $onlyOne = true;
      $files = [$files];
    } else {
      $files = array_values($files);
    }

    foreach ($files as $fileItem) {
      $filePath = "";
      $fileSize = 0;
      $fileSourceName = "";
      if (is_string($fileItem)) {
        $filePath = $fileItem;
        $fileSize = filesize($filePath);
        if (!$fileSize) {
          throw new Exception("文件保存失败", 500, "FileUpload:500001");
        }
        $fileSourceName = basename($filePath);
      } else {
        if ($fileItem['error'] > 0) {
          $uploadResult[] = $fileItem['error'];
          continue;
        }
        $fileSourceName = basename($fileItem['name']);
        $fileSize = $fileItem['size'];
        $filePath = $fileItem['tmp_name'];
      }

      $fileExtension = \pathinfo($fileSourceName, \PATHINFO_EXTENSION);
      $fileName = $fileName ?: uniqid();

      $saveFullFileName = $fileName . "." . $fileExtension;
      $saveFullPath = $savePath . "/" . $saveFullFileName;
      if (!is_dir($savePath)) {
        mkdir($savePath, 707, true);
      }
      if (is_string($fileItem)) {
        if (!file_exists($fileItem)) return false;
        $saveResult = copy($filePath, $saveFullPath);
        unlink($filePath);
      } else {
        $saveResult = \move_uploaded_file($filePath, $saveFullPath);
      }

      if (!$saveResult) {
        throw new Exception("文件保存失败", 500, "FileSave:500001", [
          "saveFullPath" => $saveFullPath,
          "filePath" => $filePath,
        ]);
      }
      $relativePath = str_replace(\F_APP_ROOT, "", $savePath);
      $fileInfo = [
        "path" => $savePath,
        "extension" => $fileExtension,
        "sourceFileName" => $fileSourceName,
        "saveFileName" => $saveFullFileName,
        "size" => $fileSize,
        "fullPath" => $saveFullPath,
        "relativePath" => $relativePath,
        "width" => 0,
        "height" => 0
      ];
      if (self::isImage($saveFullPath)) {
        $imageInfo = \getimagesize($saveFullPath);
        $fileInfo['width'] = $imageInfo[0];
        $fileInfo['height'] = $imageInfo[1];
      }
      $uploadResult[] = $fileInfo;
    }
    if ($onlyOne) {
      return $uploadResult[0];
    }

    return $uploadResult;
  }
  /**
   * 克隆目录。把指定目录下的文件和文件夹复制到指定目录
   *
   * @param string $sourcePath 被克隆的目录
   * @param string $destPath 克隆到的目标目录
   * @return void
   */
  public static function cloneDirectory($sourcePath, $destPath)
  {
    if (is_dir($sourcePath) && \is_dir($destPath)) {
      $source = \opendir($sourcePath);
      while ($handle = \readdir($source)) {
        if ($handle == "." || $handle == "..") {
          continue;
        }
        if (is_dir($sourcePath . "/" . $handle)) {
          $targetDir = $destPath . "/" . $handle;
          if (!is_dir($targetDir)) {
            mkdir($targetDir);
          }
          self::cloneDirectory($sourcePath . "/" . $handle, $targetDir);
        } else {
          copy($sourcePath . "/" . $handle, $destPath . "/" . $handle);
        }
      }
    }
  }
  /**
   * 创建文件
   *
   * @param string $filePath 文件完整路径(包含创建的文件名称和扩展名)
   * @param string $fileContent 写入的文件内容
   * @param boolean $overwrite 是否覆盖式创建。true=如果文件已经存在就不创建
   * @return boolean 创建结果
   */
  public static function createFile($filePath, $fileContent = "", $overwrite = false)
  {
    if ($overwrite === false) {
      if (\file_exists($overwrite)) {
        return true;
      }
    }
    $touchResult = \touch($filePath);
    if ($touchResult) {
      $file = \fopen($filePath, "w+");
      \fwrite($file, $fileContent);
      \fclose($file);
      return true;
    } else {
      return false;
    }
  }
  /**
   * 删除目录和目录下的文件
   *! 该方法谨慎使用，删除后无法在回收站恢复&无法撤销
   *
   * @param string $path 目录
   * @return boolean 删除结果
   */
  public static function deleteDirectory($path)
  {
    if (is_dir($path)) {
      $directorys = @\scandir($path);
      foreach ($directorys as $directoryItem) {
        if ($directoryItem === "." || $directoryItem === "..") {
          continue;
        }
        $directoryItem = $path . "/" . $directoryItem;
        if (is_dir($directoryItem)) {
          self::deleteDirectory($directoryItem);
          // @rmdir($directoryItem);
        } else {
          @unlink($directoryItem);
        }
      }
      @rmdir($path);
      return true;
    } else {
      return false;
    }
  }
  /**
   * 创建文件夹
   *
   * @param array $dirs 路径项数组
   * @param string $baseDir 基目录，也就是基于该目录创建文件夹
   * @return bool
   */
  public static function mkdir($dirs, $baseDir = "", $permissions = 0757)
  {
    return mkdir(self::genPath($baseDir, ...$dirs), $permissions, true);
  }
  /**
   * 生成一个路径字符串
   *
   * @param string ...$els 路径项
   * @return string 生成后的路径
   */
  public static function genPath(...$els)
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
    }, array_filter($els, function ($item) {
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
   * 清除文件夹里面的全部内容
   *
   * @param string $targetPath 被清除的文件夹路径
   * @param array $whiteList 清除是跳过的白名单。数组的元素必须是完整的目录，也就是包含$destPath，例如 $destPath = "a/b" 那么白名单的元素就是 a/b/c/d 就会跳过路径是 /a/b/c/d 的文件或者目录
   * @return boolean 清除成功？
   */
  public static function clearFolder($targetPath, $whiteList = [])
  {
    if (!is_dir($targetPath)) return false;

    $files = self::scandir($targetPath);
    if (count($files) === 0) return 0;
    foreach ($files as $fileItem) {
      $path = self::genPath($targetPath, $fileItem);
      if (in_array($path, $whiteList)) continue;

      if (is_dir($path)) {
        self::clearFolder($path, $whiteList);
        rmdir($path);
        // self::deleteDirectory($path);
      } else {
        unlink($path);
      }
    }

    return true;
  }
  /**
   * 复制 targetPath 的文件、文件夹到 $destPath 目录
   *
   * @param string $targetPath 被复制的目录
   * @param string $destPath 复制 到 的目录
   * @param array $whiteList 路径白名单，会跳过数组里面的白名单。数组的元素必须是完整的目录，也就是包含$destPath，例如 $destPath = "a/b" 那么白名单的元素就是 a/b/c/d 就会跳过路径是 /a/b/c/d 的文件或者目录
   * @return boolean 复制成功？
   */
  public static function copyFolder($targetPath, $destPath, $whiteList = [])
  {
    if (!is_dir($targetPath)) {
      return false;
    }
    if (!is_dir($destPath)) {
      mkdir($destPath, 0757, true);
    }

    $files = self::scandir($targetPath);
    if (count($files) === false) return false;

    $result = true;
    foreach ($files as $fileItem) {
      $pathItem = self::genPath($targetPath, $fileItem);
      $destPathItem = self::genPath($destPath, $fileItem);
      if (in_array($destPath, $whiteList)) continue;

      if (is_dir($pathItem)) {
        $operationResult = self::copyFolder($pathItem, $destPathItem);
        if ($operationResult === false) {
          if (is_dir($destPath)) {
            self::deleteDirectory($destPath);
          }
        }
      } else {
        $operationResult = copy($pathItem, $destPathItem);
      }
      if (!$operationResult) {
        $result = false;
        break;
      }
    }

    if (!$result) {
      self::deleteDirectory($destPath);
    }

    return $result;
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
        if (!self::compareDirectories(self::genPath($targetPath, $targetFileItem),  self::genPath($targetPath, $sourceFiles[$index]))) {
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
   * @param boolean $includeDir 包含文件夹路径
   * @return string[] 扫描后的文件列表，没有分层
   */
  public static function recursionScanDir($rootDir, $includeDir = false)
  {
    if (!is_dir($rootDir)) return [];
    $dirs = self::scandir($rootDir);
    $allDirs = [];
    foreach ($dirs as $dir) {
      if (is_dir(File::genPath($rootDir, $dir))) {
        $allDirs = array_merge($allDirs, self::recursionScanDir(File::genPath($rootDir, $dir)));
        if ($includeDir) {
          array_push($allDirs, File::genPath($rootDir, $dir));
        }
      } else {
        array_push($allDirs, File::genPath($rootDir, $dir));
      }
    }
    return $allDirs;
  }
}
