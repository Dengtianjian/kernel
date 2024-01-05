<?php

namespace kernel\Platform\DiscuzX\Service\File;

use kernel\Foundation\File\FileRemoteOSSStorage;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files\FileStorage as DiscuzXFileStorageNamespace;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Service\File\FileOSSStorageService;

class DiscuzXOSSService extends FileOSSStorageService
{
  /**
   * 文件存储表模型实例
   * 
   * @var DiscuzXFilesModel
   */
  protected static $FilesModelInstance = null;

  static function useService($OSSPlatform = FileRemoteOSSStorage::OSS_QCLOUD, $SecretId = null, $SecretKey = null, $Region = null, $Bucket = null, $SignatureKey = null)
  {
    parent::useService($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey);

    Router::get("fileStorage/oss/upload/auth", DiscuzXFileStorageNamespace\DiscuzXFileRemoteStorageOSSGetUploadAuthController::class);
  }
}
