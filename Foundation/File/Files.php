<?php

namespace kernel\Foundation\File;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\URL;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class Files
{
  /**
   * 上传文件，并且保存在服务器
   *
   * @param File|string $file 文件
   * @param string $savePath 保存的路径，相对于F_APP_STORAGE
   * @param string $fileName 存储的文件名称。如果未传入该值，将会自动生成新的文件名称
   * @return false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int}
   */
  public static function upload($file, $savePath, $fileName = null)
  {
    if (!$file) return false;
    $filePath = "";
    $fileSize = 0;
    $fileSourceName = "";

    if (is_string($file)) {
      $filePath = $file;
      $fileSize = filesize($filePath);
      if (!$fileSize) {
        throw new Exception("文件保存失败", 500, "FileUpload:500001");
      }
      $fileSourceName = basename($filePath);
    } else {
      if ($file['error'] > 0) {
        throw new Exception("文件保存失败", 400, "FileUpload:400001:", $file['error']);
      }
      $fileSourceName = basename($file['name']);
      $fileSize = $file['size'];
      $filePath = $file['tmp_name'];
    }

    $fileExtension = \pathinfo($fileSourceName, \PATHINFO_EXTENSION);
    if ($fileName) {
      $fileNameInfo = pathinfo($fileName);
      $fileName = $fileNameInfo['filename'];
      $fileExtension = $fileNameInfo['extension'];
    } else {
      $fileName = uniqid();
    }

    $saveFullFileName = "{$fileName}.{$fileExtension}";
    $path = $saveFullFileName;
    if ($savePath) {
      $path = FileHelper::combinedFilePath($savePath, $saveFullFileName);
      $FolderPath = FileHelper::combinedFilePath(F_APP_STORAGE, $savePath);
      if (!is_dir($FolderPath)) {
        mkdir($FolderPath, 770, true);
      }
    }

    $saveFullPath = FileHelper::combinedFilePath(F_APP_STORAGE, $path);
    if (is_string($file)) {
      if (!file_exists($file)) {
        throw new Exception("文件保存失败", 500, "FileUpload:500002");
      }
      $saveResult = copy($filePath, $saveFullPath);
      unlink($filePath);
    } else {
      $saveResult = \move_uploaded_file($filePath, $saveFullPath);
    }

    if (!$saveResult) {
      throw new Exception("文件保存失败", 500, "FileSave:500003", [
        "saveFullPath" => $saveFullPath,
        "filePath" => $filePath,
      ]);
    }

    $fileInfo = [
      "fileKey" => self::combinedFileKey($savePath, $saveFullFileName),
      "sourceFileName" => $fileSourceName,
      "path" => FileHelper::optimizedPath($savePath),
      "fileName" => $saveFullFileName,
      "extension" => $fileExtension,
      "size" => $fileSize,
      "fullPath" => FileHelper::optimizedPath($saveFullPath),
      "relativePath" => FileHelper::optimizedPath($path),
      "width" => 0,
      "height" => 0
    ];
    if (FileHelper::isImage($saveFullPath)) {
      $imageInfo = \getimagesize($saveFullPath);
      $fileInfo['width'] = $imageInfo[0];
      $fileInfo['height'] = $imageInfo[1];
    }

    return $fileInfo;
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
   * 清除文件夹里面的全部内容
   *
   * @param string $targetPath 被清除的文件夹路径
   * @param array $whiteList 清除是跳过的白名单。数组的元素必须是完整的目录，也就是包含$destPath，例如 $destPath = "a/b" 那么白名单的元素就是 a/b/c/d 就会跳过路径是 /a/b/c/d 的文件或者目录
   * @return boolean 清除成功？
   */
  public static function clearFolder($targetPath, $whiteList = [])
  {
    if (!is_dir($targetPath)) return false;

    $files = FileHelper::scandir($targetPath);
    if (count($files) === 0) return 0;
    foreach ($files as $fileItem) {
      $path = FileHelper::combinedFilePath($targetPath, $fileItem);
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

    $files = FileHelper::scandir($targetPath);
    if (count($files) === false) return false;

    $result = true;
    foreach ($files as $fileItem) {
      $pathItem = FileHelper::combinedFilePath($targetPath, $fileItem);
      $destPathItem = FileHelper::combinedFilePath($destPath, $fileItem);
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
   * 通过文件路径、文件名称组合成一个文件键名
   *
   * @param string $filePath 文件路径
   * @param string $fileName 文件名称
   * @param boolean $encode 对文件名进行编码
   * @return string 文件键名
   */
  static function combinedFileKey($filePath, $fileName, $encode = false)
  {
    $filePath = str_replace("\\", "/", $filePath);
    $fileName = str_replace("\\", "/", $fileName);

    $fileKey = implode("/", [
      $filePath,
      $fileName
    ]);
    if (substr($fileKey, 0, 1) === "/") {
      $fileKey = substr($fileKey, 1);
    }

    if ($encode) {
      $fileKey = rawurlencode($fileKey);
    }

    return $fileKey;
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @return false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int} 文件信息
   */
  static function getFileInfo($FileKey)
  {
    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (!file_exists($FilePath)) {
      return 0;
    }

    $FileInfo = pathinfo($FilePath);
    $File = [
      "fileKey" => $FileKey,
      "path" => dirname($FileKey),
      "fileName" => $FileInfo['filename'],
      "extension" => $FileInfo['extension'],
      "size" => filesize($FilePath),
      "fullPath" => $FilePath,
      "relativePath" => FileHelper::optimizedPath(dirname($FileKey)),
      "width" => 0,
      "height" => 0
    ];
    if (FileHelper::isImage($FilePath)) {
      $imageInfo = \getimagesize($FilePath);
      $File['width'] = $imageInfo[0];
      $File['height'] = $imageInfo[1];
    }

    return true;
  }
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @return booleanƒ 是否已删除，true=删除完成，false=删除失败
   */
  static function deleteFile($FileKey)
  {
    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (file_exists($FilePath)) {
      unlink($FilePath);
    }

    return true;
  }
  /**
   * 获取访问链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @return string 访问URL
   */
  static function getFilePreviewURL($FileKey, $URLParams = [])
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "files/{$FileKey}/preview";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  /**
   * 获取下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @return string 下载URL
   */
  static function getFileDownloadURL($FileKey, $URLParams = [])
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "files/{$FileKey}/download";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
