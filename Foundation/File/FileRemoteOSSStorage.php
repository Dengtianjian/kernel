<?php

namespace kernel\Foundation\File;

use kernel\Foundation\Exception\Exception;
use kernel\Service\OSS\OSSQcloudCosService;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FileRemoteOSSStorage extends FileRemoteStorage
{
  const OSS_PLATFORMS = ["QCloudCOS", "AliYunOSS"];
  const OSS_QCLOUD = "QCloudCOS";
  const OSS_ALIYUN = "AliYunOSS";

  /**
   * 实例化对象存储服务类
   *
   * @param "QCloudCOS"|"AliYunOSS" $OSSPlatform
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   * @param string $SignatureKey 签名秘钥
   */
  public function __construct($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey)
  {
    switch ($OSSPlatform) {
      case self::OSS_QCLOUD:
        $this->RemoteStorageInstance = new OSSQcloudCosService($SecretId, $SecretKey, $Region, $Bucket);
        break;
      case self::OSS_ALIYUN:
        break;
      default:
        throw new Exception("该OSS平台不支持");
        break;
    }

    parent::__construct($SignatureKey);

    $this->FileStorageInstance = $this;
  }
}
