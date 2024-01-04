<?php

namespace kernel\Service\File;

use kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS as FileRemoteStorageOSSNamespace;
use kernel\Controller\Main\Files\FileStorage as FileStorageNamespace;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileRemoteOSSStorage;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Model\FilesModel;
use kernel\Service\File\FileRemoteStorageService;

class FileOSSStorageService extends FileRemoteStorageService
{
  /**
   * 远程存储服务实例
   *
   * @var FileRemoteOSSStorage
   */
  protected static $FileStorageInstance = null;

  /**
   * 实例化OSS服务类
   *
   * @param "QCloudCos"|"AliYunOSS" $OSSPlatform
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   * @param string $SignatureKey 签名秘钥
   */
  static function useService($OSSPlatform = FileRemoteOSSStorage::OSS_QCLOUD, $SecretId = null, $SecretKey = null, $Region = null, $Bucket = null, $SignatureKey = null)
  {
    if (!$OSSPlatform || !in_array($OSSPlatform, FileRemoteOSSStorage::OSS_PLATFORMS)) {
      throw new Exception("该OSS平台不支持");
    }
    if (!$SecretId) {
      throw new Exception("使用OSS服务请传入OSS平台SecretId参数");
    }
    if (!$SecretKey) {
      throw new Exception("使用OSS服务请传入OSS平台SecretKey参数");
    }
    if (!$Region) {
      throw new Exception("使用OSS服务请传入OSS平台Region参数");
    }
    if (!$Bucket) {
      throw new Exception("使用OSS服务请传入OSS平台Bucket参数");
    }

    Router::get("fileStorage/oss/upload/auth", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSGetUploadAuthController::class);
    Router::post("fileStorage/upload/auth", FileStorageNamespace\FileStorageGetUploadFileAuthController::class);
    Router::post("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageUploadFileController::class);
    Router::delete("fileStorage/{fileId:.+?}", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSDeleteFileController::class);
    Router::get("fileStorage/{fileId:.+?}/preview", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSAccessFileController::class);
    Router::get("fileStorage/{fileId:.+?}/download", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSDownloadFileController::class);
    Router::get("fileStorage/{fileId:.+?}", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSGetFileController::class);

    self::$FileStorageInstance = new FileRemoteOSSStorage($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket);

    parent::useService(null, $SignatureKey);
  }

  static function deleteFile($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "delete")
  {
    $R = new ReturnResult(true);

    if ($Signature && !self::$FileStorageInstance->verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders, $HTTPMethod)) {
      return $R->error(403, 403, "无权删除");
    }

    return $R->success(self::$FileStorageInstance->deleteFile($FileKey, $Signature, $CurrentAuthId, $RawURLParams, $RawHeaders, $HTTPMethod));
  }

  static function getFileInfo($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);

    if ($Signature && !self::$FileStorageInstance->verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders, $HTTPMethod)) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      return $R->error(403, 403, "无权获取");
    }

    $FileInfo = self::$FileStorageInstance->getFileInfo($FileKey, $Signature, $CurrentAuthId, $RawURLParams, $RawHeaders, $HTTPMethod);

    if (is_bool($FileInfo)) {
      return $R->error(500, 500, "获取文件信息失败", $FileInfo);
    }
    if (is_numeric($FileInfo)) {
      switch ($FileInfo) {
        case 0:
          return $R->error(403, 403001, "签名错误", $FileInfo);
        case 1:
          return $R->error(404, 404001, "文件不存在", [], false);
        case 2:
          return $R->error(403, 403002, "无权访问");
        case 3:
          return $R->error(403, 403003, "无权访问");
        case 4:
          return $R->error(404, 404002, "文件不存在");
      }
    }

    $width = $FileInfo['width'];
    $height = $FileInfo['height'];
    $size = $FileInfo['fileSize'];
    if ($FileInfo['remote']) {
      if (!$width || !$height) {
        $ImageInfo = self::$FileStorageInstance->getImageInfo($FileKey);
        if ($ImageInfo === false) {
          return $R->error(500, 500, "获取远程文件信息失败", [], $ImageInfo);
        }
        if (!is_null($ImageInfo)) {
          $FileInfo['width'] = $width = (int)$ImageInfo['width'];
          $FileInfo['height'] = $height = (int)$ImageInfo['height'];
          $FileInfo['fileSize'] = $size = (float)$ImageInfo['size'];

          FilesModel::singleton()->update([
            "width" => $width,
            "height" => $height,
            "fileSize" => $size
          ]);
        }
      }
    } else {
      $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));

      if (!file_exists($FilePath)) {
        return $R->error(404, 404003, "文件不存在", [], false);
      }

      if (FileHelper::isImage($FilePath) && (!$width || !$height)) {
        $ImageInfo = getimagesize($FilePath);
        if ($ImageInfo) {
          $FileInfo['width'] = $width = (int)$ImageInfo[0];
          $FileInfo['height'] = $height = (int)$ImageInfo[1];
          $FileInfo['fileSize'] = $size = (float)filesize($FilePath);

          FilesModel::singleton()->update([
            "width" => $width,
            "height" => $height,
            "fileSize" => $size
          ]);
        }
      }
    }

    return $R->success($FileInfo);
  }

  /**
   * 获取访问授权信息字符串
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @param boolean $Remote 是否是远程文件
   * @return ReturnResult{string} URL请求参数格式的授权信息字符串
   */
  static function getAccessAuth($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $HTTPMethod = "get", $Remote = true)
  {
    $R = new ReturnResult(null);

    if (!$Remote) {
      $R->success(parent::getAccessAuth($FileKey, $Expires, $URLParams, $HTTPMethod, true));
    } else {
      $R->success(self::$FileStorageInstance->generateAccessAuth($FileKey, $Expires, $URLParams, $HTTPMethod, $Headers));
    }

    return $R;
  }
  /**
   * 获取对象访问地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求查询参数
   * @param boolean $WithSignature 获取到URL是否携带签名
   * @param integer $Expires 授权有效期
   * @param string $HTTPMethod 请求方式
   * @param boolean $Remote 是否为远程文件
   * @param boolean $Download 访问时下载文件
   * @return ReturnResult{string} 访问URL地址
   */
  static function getAccessURL($FileKey, $URLParams = [], $WithSignature = TRUE, $Expires = 600, $HTTPMethod = "get", $Remote = true, $Download = false)
  {
    $R = new ReturnResult(null);

    if ($Remote) {
      $R->success(self::$FileStorageInstance->generateAccessURL($FileKey, $Expires, $URLParams, [], [], $Download));
    } else {
      $R->success(parent::getAccessURL($FileKey, $URLParams, $WithSignature, $Expires, $HTTPMethod));
    }

    return $R;
  }
}
