<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\Driver\OSSQCloudCOSFileDriver;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
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
   * @param string $Record 存储的文件信息是否存入数据库
   * @param string $FileKeyRemotePrefix 远程文件名前缀标识，值为NULL或者FALSE就是不增加远程前缀标识  
   * @param string $RoutePrefix 路由前缀
   */
  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $SignatureKey, $Record = TRUE, $FileKeyRemotePrefix = NULL, $RoutePrefix = "files")
  {
    parent::__construct($SecretId, $SecretKey, $Region, $Bucket, $SignatureKey, $Record, $FileKeyRemotePrefix, $RoutePrefix);

    $this->DiscuzXFileStorageDriver = new DiscuzXFileStorageDriver($SignatureKey, $Record, $RoutePrefix);
    if ($Record) {
      $this->filesModel = new DiscuzXFilesModel();
    }
  }
  public function uploadFile($File, $fileKey = null, $ownerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::AUTHENTICATED_READ)
  {
    if (is_null($ownerId)) {
      $ownerId = getglobal("uid");
    }
    return parent::uploadFile($File, $fileKey, $ownerId, $BelongsId, $BelongsType, $ACL);
  }
  public function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $WithAccessControl = TRUE)
  {
    return $this->DiscuzXFileStorageDriver->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature, $WithAccessControl);
  }
  public function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $WithAccessControl = TRUE)
  {
    return $this->DiscuzXFileStorageDriver->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature, $WithAccessControl);
  }
  public function verifyRequestAuth($FileKey)
  {
    return $this->DiscuzXFileStorageDriver->verifyRequestAuth($FileKey);
  }
}
