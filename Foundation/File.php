<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class File
{
  /**
   * 判断文件是不是视频文件
   *
   * @param string $fileName 文件路径(包含文件名和扩展名) root/path/show.mp4
   * @param array|string $extensions? 校验的扩展名。如果传入了就会校验文件是否是指定的扩展名
   * @return boolean 是视频返回true，否则返回false
   */
  public static function isVideo($fileName, $extensions = null)
  {
    if ($extensions === null) {
      $extensions = ["mp4"];
    }
    $fileExtension = \pathinfo($fileName, \PATHINFO_EXTENSION);
    if (is_array($extensions)) {
      return \in_array($fileExtension, $extensions);
    } else {
      $extensions = \strtolower($extensions);
      return \strpos($fileName, $extensions) !== false;
    }
  }
  /**
   * 判断文件是否是图片文件
   *
   * @param string $fileName 文件路径(包含文件名和扩展名) root/path/image.png
   * @param array|string $extensions? 校验的扩展名。如果传入了就会校验文件是否是指定的扩展名
   * @return boolean 是图片返回true，否则返回false
   */
  public static function isImage($fileName, $extensions = null)
  {
    if ($extensions !== null) {
      $fileExtension = \pathinfo($fileName, \PATHINFO_EXTENSION);
      if (is_array($extensions)) {
        return \in_array($fileExtension, $extensions);
      } else {
        $fileExtension = \strtolower($fileExtension);
        return \strpos($fileName, $fileExtension) !== false;
      }
    } else {
      if (\function_exists("exif_imagetype")) {
        $info = \exif_imagetype($fileName);
        if ($info === false) {
          return false;
        }
      } else {
        $info = @\getimagesize($fileName);
        if (!$info) {
          return false;
        }
      }
      return true;
    }
  }
  /**
   * 上传文件，并且保存在服务器
   *
   * @param array|string $files 文件或者多个文件数组
   * @param string $savePath 保存的完整路径
   * @return array
   */
  public static function upload($files, $savePath)
  {
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
          Response::error(500, "File:500002", "保存文件失败", [], error_get_last());
        }
        $fileSourceName = $filePath;
      } else {
        if ($fileItem['error'] > 0) {
          $uploadResult[] = $fileItem['error'];
          continue;
        }
        $fileSourceName = $fileItem['name'];
        $fileSize = $fileItem['size'];
        $filePath = $fileItem['tmp_name'];
      }

      $fileExtension = \pathinfo($fileSourceName, \PATHINFO_EXTENSION);
      $fileCode = \mt_rand(1000, 9999) . time();
      $saveFullFileName = $fileCode . "." . $fileExtension;
      $saveFullPath = $savePath . "/" . $saveFullFileName;
      if (is_string($fileItem)) {
        if (!file_exists($fileItem)) return false;
        $saveResult = copy($filePath, $saveFullPath);
        unlink($filePath);
      } else {
        $saveResult = @\move_uploaded_file($filePath, $saveFullPath);
      }

      if (!$saveResult) {
        Response::error(500, "File:500001", "保存文件失败", [], error_get_last());
      }
      $relativePath = str_replace(\F_APP_ROOT, "", $savePath);
      $fileInfo = [
        "path" => $savePath,
        "extension" => $fileExtension,
        "sourceFileName" => $fileSourceName,
        "saveFileName" => $saveFullFileName,
        "size" => $fileSize,
        "fullPath" => $saveFullPath,
        "relativePath" => $relativePath
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
  public static function deleteDirectory($path): bool
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
          @rmdir($directoryItem);
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
  public static function mkdir(array $dirs, $baseDir = "")
  {
    if (count($dirs) === 0) {
      return true;
    }
    if (!is_dir($baseDir)) {
      mkdir($baseDir);
    }
    $baseDir .= "/" . $dirs[0];
    if (!is_dir($baseDir)) {
      mkdir($baseDir);
    }
    array_splice($dirs, 0, 1);
    return self::mkdir($dirs, $baseDir);
  }
}
