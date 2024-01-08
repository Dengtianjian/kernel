<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\Driver\OSSQCloudCOSFileDriver;
use kernel\Service\OSS\OSSQcloudCosService;

class DiscuzXOSSQCloudCOSFileDriver extends OSSQCloudCOSFileDriver
{
  /**
   * DiscuzX平台的文件存储驱动
   *
   * @var DiscuzXFileStorageDriver
   */
  protected $DiscuzXFileStorageDriver = null;

  /**
   * 实例化腾讯云COS存储驱动
   *
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   * @param string $SignatureKey 本地存储签名秘钥
   */
  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $SignatureKey, $Record = TRUE)
  {
    parent::__construct($SecretId, $SecretKey, $Region, $Bucket, $SignatureKey, $Record);

    $this->DiscuzXFileStorageDriver = new DiscuzXFileStorageDriver(true, $SignatureKey, $Record);
  }
  public function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->DiscuzXFileStorageDriver->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  public function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->DiscuzXFileStorageDriver->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
}
