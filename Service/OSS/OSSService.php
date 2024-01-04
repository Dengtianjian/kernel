<?php

namespace kernel\Service\OSS;

use kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS as FileRemoteStorageOSSNamespace;
use kernel\Controller\Main\Files\FileStorage as FileStorageNamespace;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Model\FilesModel;
use kernel\Service\File\FileRemoteStorage\FileRemoteStorageService;

class OSSService extends FileRemoteStorageService
{
  /**
   * 远程存储服务实例
   *
   * @var ObjectStorageService
   */
  protected static $RemoteStorageInstance = null;

  /**
   * 实例化OSS服务类
   *
   * @param "QCloudCos"|"AliYunOSS" $OSSPlatform
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   */
  static function useService($OSSPlatform = ObjectStorageService::OSS_QCLOUD, $SecretId = null, $SecretKey = null, $Region = null, $Bucket = null)
  {
    if (!$OSSPlatform || !in_array($OSSPlatform, ObjectStorageService::OSS_PLATFORMS)) {
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

    self::$RemoteStorageInstance = new ObjectStorageService($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket);
  }

  static function addFile($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $OwnerId = null, $ACL = FileStorage::PRIVATE, $extension = null, $Width = null, $Height = null, $BelongsId = null, $BelongsType = null)
  {
    if (!$extension) {
      $extension = pathinfo($SourceFileName, PATHINFO_EXTENSION);
    }

    return FilesModel::singleton()->add($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $extension, $OwnerId, $ACL, true, $BelongsId, $BelongsType, $Width, $Height);
  }

  static function deleteFile($FileKey, $Signature = null, $SignatureKey = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "delete")
  {
    $R = new ReturnResult(true);

    $FS = new FilesModel();
    $File = $FS->item($FileKey);
    if (!$File) {
      return $R->error(404, 404001, "文件不存在", [], false);
    }
    $DeletedResult = parent::deleteFile($FileKey);
    if ($DeletedResult->error) return $DeletedResult;

    self::$RemoteStorageInstance->deleteObject($FileKey);

    return $DeletedResult;
  }

  static function getFileInfo($FileKey, $Signature = null, $SignatureKey = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return $R->error(403, 403001, "签名错误", $verifyResult);
    }

    $File = FilesModel::singleton()->item($FileKey);
    if (!$File) {
      return $R->error(404, 404001, "文件不存在", [], false);
    }

    if ($File['acl'] === FileStorage::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return $R->error(403, 403002, "无权访问", [], $File['acl']);
      }
    } else {
      if (!$Signature) {
        if (in_array($File['acl'], [
          FileStorage::AUTHENTICATED_READ,
          FileStorage::AUTHENTICATED_READ_WRITE
        ])) {
          return $R->error(403, 403003, "无权访问", [], $File['acl']);
        }
      }
    }

    $width = $File['width'];
    $height = $File['height'];
    $size = $File['fileSize'];
    if ($File['remote']) {
      if (!$width || !$height) {
        $ImageInfo = self::$RemoteStorageInstance->getImageInfo($FileKey);
        if ($ImageInfo === false) {
          return $R->error(500, 500, "获取远程文件信息失败", [], $ImageInfo);
        }
        if (!is_null($ImageInfo)) {
          $width = (int)$ImageInfo['width'];
          $height = (int)$ImageInfo['height'];
          $size = (float)$ImageInfo['size'];

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
          $width = (int)$ImageInfo[0];
          $height = (int)$ImageInfo[1];
          $size = (float)filesize($FilePath);

          FilesModel::singleton()->update([
            "width" => $width,
            "height" => $height,
            "fileSize" => $size
          ]);
        }
      }
    }

    return $R->success([
      "fileKey" => $FileKey,
      "path" => $File['filePath'],
      "fileName" => $File['fileName'],
      "extension" => $File['extension'],
      "size" => $size,
      "fullPath" => $FilePath,
      "relativePath" => FileHelper::optimizedPath(dirname($FileKey)),
      "ownerId" => $File['ownerId'],
      "width" => $width,
      "height" => $height,
      'acl' => $File['acl'],
      "remote" => $File['remote'],
      "createdAt" => $File['createdAt'],
      "updatedAt" => $File['updatedAt']
    ]);
  }

  /**
   * 获取访问授权信息字符串
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @param string $SignatureKey 签名秘钥
   * @param boolean $Remote 是否是远程文件
   * @return ReturnResult{string} URL请求参数格式的授权信息字符串
   */
  static function getAccessAuth($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $HTTPMethod = "get", $SignatureKey = null, $Remote = true)
  {
    $R = new ReturnResult(null);

    if (!$Remote) {
      $R->success(parent::getAccessAuth($FileKey, $SignatureKey, $Expires, $URLParams, $HTTPMethod, true));
    } else {
      $R->success(self::$RemoteStorageInstance->getObjectAuth($FileKey, $HTTPMethod, $Expires, $URLParams, $Headers));
    }

    return $R;
  }
  /**
   * 获取对象访问地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求查询参数
   * @param integer $Expires 授权有效期
   * @param string $SignatureKey 签名秘钥，该参数只有在不是远程文件时才会使用到
   * @param string $HTTPMethod 请求方式
   * @param boolean $Remote 是否为远程文件
   * @param boolean $Download 访问时下载文件
   * @return ReturnResult{string} 访问URL地址
   */
  static function getAccessURL($FileKey, $URLParams = [], $Expires = 600, $SignatureKey = NULL, $HTTPMethod = "get", $Remote = true, $Download = false)
  {
    $R = new ReturnResult(null);

    if ($Remote) {
      $R->success(self::$RemoteStorageInstance->getObjectURL($FileKey, $Expires, $URLParams, [], [], $Download));
    } else {
      $R->success(parent::getAccessURL($FileKey, $URLParams, $SignatureKey, $Expires, $HTTPMethod));
    }

    return $R;
  }
}
