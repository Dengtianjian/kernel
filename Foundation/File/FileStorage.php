<?php

namespace kernel\Foundation\File;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\URL;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FileStorage
{
  /**
   * 私有的，创作者与管理员具备全部权限，其他人没有权限
   */
  const PRIVATE = "private";
  /**
   * 共有读的，匿名用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const PUBLIC_READ = "public-read";
  /**
   * 公有读写，创建者、管理员和匿名用户具备全部权限，通常不建议授予此权限
   */
  const PUBLIC_READ_WRITE = "public-read-write";
  /**
   * 认证用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const AUTHENTICATED_READ = "authenticated-read";
  /**
   * 创建者、管理员和认证用户具备全部权限，通常不建议授予此权限
   */
  const AUTHENTICATED_READ_WRITE = "authenticated-read-write";

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
      if (FileHelper::isImage($saveFullPath)) {
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
   * @return string 文件键名
   */
  static function combinedFileKey($filePath, $fileName)
  {
    $filePath = str_replace("\\", "/", $filePath);
    $fileName = str_replace("\\", "/", $fileName);

    return implode("/", [
      $filePath,
      $fileName
    ]);
  }
  /**
   * 生成访问授权信息
   *
   * @param string $FilePath 文件路径
   * @param string $FileName 文件名称
   * @param string $SignatureKey 签名秘钥
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param string $AuthId 授权ID。一般用于场景是，当前签名只允许给某个用户使用，就可传入该值；校验签名时也需要传入该值，并且校验请求参数的AuthId是否和传入的AuthId一致，不一致就是校验不通过。
   * @param string $HTTPMethod 请求方式
   * @param string $ACL 访问权限控制
   * @return string 授权信息
   */
  static function generateAccessAuth($FilePath, $FileName, $SignatureKey, $Expires = 600, $URLParams = [], $AuthId = null, $HTTPMethod = "get", $ACL = self::PRIVATE)
  {
    $FSS = new FileStorageSignature($SignatureKey);
    $FileKey = self::combinedFileKey($FilePath, $FileName);

    $URLParams['acl'] = $ACL;
    if ($AuthId) {
      $URLParams['authId'] = $AuthId;
    }

    $Signature = $FSS->createAuthorization($FileKey, $URLParams, [], $Expires, $HTTPMethod, false);

    return URL::buildQuery($Signature, false);
  }
  /**
   * 验证授权签名
   *
   * @param string $SignatureKey 签名秘钥
   * @param string $FileKey 文件名称
   * @param array $RawURLParams 请求参数
   * @param array $RawHeaders 请求头
   * @param string $AuthId 授权ID，用于校验请求参数中的AuthId是否与当前值一致
   * @param string $HTTPMethod 请求方式
   * @return boolean true验证通过，false失败
   */
  static function verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders = [], $AuthId = null, $HTTPMethod = "get")
  {
    $URLParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    foreach ($URLParamKeys as $key) {
      if (!array_key_exists($key, $RawURLParams)) {
        return false;
      }
    }
    $SignAlgorithm = $RawURLParams['sign-algorithm'];
    $SignTime = $RawURLParams['sign-time'];
    $KeyTime = $RawURLParams['key-time'];
    $HeaderList = explode(";", urldecode($RawURLParams['header-list']));
    $URLParamList = explode(";", rawurldecode(urldecode($RawURLParams['url-param-list'])));
    $URLAuthId = rawurldecode(urldecode($RawURLParams['authId']));
    $Signature = $RawURLParams['signature'];

    if ((!is_null($AuthId) || array_key_exists("authId", $RawURLParams)) && $URLAuthId !== !$AuthId) return false;

    if ($SignAlgorithm !== FileStorageSignature::getSignAlgorithm()) return false;
    if (strpos($SignTime, ";") === false || strpos($KeyTime, ";") === false) return false;
    list($startTime, $endTime) = explode(";", $SignTime);
    $startTime = intval($startTime);
    $endTime = intval($endTime);
    if ($endTime < $startTime) return false;
    if ($endTime < time()) return false;

    $Headers = [];
    foreach ($RawHeaders as $key => $value) {
      $key = rawurldecode(urldecode($key));
      $value = rawurldecode(urldecode($value));
      if (!array_key_exists($key, $HeaderList)) {
        return false;
      }
      $Headers[$key] = $value;
    }

    $URLParams = [];
    foreach ($RawURLParams as $key => $value) {
      $key = rawurldecode(urldecode($key));
      $value = rawurldecode(urldecode($value));
      if (!in_array($key, $URLParamList)) {
        if (!in_array($key, $URLParamKeys)) {
          return false;
        }
      }
      if (!in_array($key, $URLParamKeys)) {
        $URLParams[$key] = $value;
      }
    }

    return FileStorageSignature::call($SignatureKey)->verifyAuthorization($Signature, $FileKey, $startTime, $endTime, $URLParams, $Headers, $HTTPMethod);
  }
  /**
   * 生成访问链接
   *
   * @param string $FilePath 文件路径
   * @param string $FileName 文件名称
   * @param string $SignatureKey 签名秘钥
   * @param integer $Expires 有效期，秒级
   * @param array $URLParams 请求参数
   * @param string $AuthId 授权ID。一般用于场景是，当前签名只允许给某个用户使用，就可传入该值；校验签名时也需要传入该值，并且校验请求参数的AuthId是否和传入的AuthId一致，不一致就是校验不通过。
   * @param string $HTTPMethod 请求方式
   * @param string $ACL 访问控制
   * @return string 访问URL
   */
  static function generateAccessURL($FilePath, $FileName, $SignatureKey = null, $Expires = 600, $URLParams = [], $AuthId = null, $HTTPMethod = "get", $ACL = self::PRIVATE)
  {
    $FileKey = rawurlencode(self::combinedFileKey($FilePath, $FileName));
    $queryString = "";
    if ($SignatureKey) {
      $queryString = "?" . self::generateAccessAuth($FilePath, $FileName, $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, $ACL);
    }

    return F_BASE_URL . "/files/{$FileKey}{$queryString}";
  }
}
