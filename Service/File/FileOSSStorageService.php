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

    self::$FileStorageInstance = new FileRemoteOSSStorage($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey);

    parent::useService(null, $SignatureKey);
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
