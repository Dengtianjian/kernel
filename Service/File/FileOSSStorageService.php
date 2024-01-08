<?php

namespace kernel\Service\File;

use kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS as FileRemoteStorageOSSNamespace;
use kernel\Controller\Main\Files\FileStorage as FileStorageNamespace;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileRemoteOSSStorage;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Service\File\FileRemoteStorageService;

class FileOSSStorageService extends FileRemoteStorageService
{
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

    // Router::post("fileStorage/upload/auth", FileStorageNamespace\FileStorageGetUploadFileAuthController::class);
    // Router::post("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageUploadFileController::class);

    // Router::get("fileStorage/oss/upload/auth", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSGetUploadAuthController::class);
    // Router::delete("fileStorage/{fileId:.+?}", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSDeleteFileController::class);
    // Router::get("fileStorage/{fileId:.+?}/preview", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSAccessFileController::class);
    // Router::get("fileStorage/{fileId:.+?}/download", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSDownloadFileController::class);
    // Router::get("fileStorage/{fileId:.+?}", FileRemoteStorageOSSNamespace\FileRemoteStorageOSSGetFileController::class);

    parent::useService($SignatureKey);

    self::$FileRemoteStorageInstance = new FileRemoteOSSStorage($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey);
  }
}
