<?php

namespace kernel\Platform\DiscuzX\Service\File;

use kernel\Foundation\File\FileRemoteOSSStorage;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files\FileStorage as DiscuzXFileStorageNamespace;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileRemoteOSSStorage;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileRemoteStorage;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Service\File\FileOSSStorageService;

class DiscuzXOSSService extends FileOSSStorageService
{
  static function useService($OSSPlatform = FileRemoteOSSStorage::OSS_QCLOUD, $SecretId = null, $SecretKey = null, $Region = null, $Bucket = null, $SignatureKey = null)
  {
    Router::get("fileStorage/oss/upload/auth", DiscuzXFileStorageNamespace\DiscuzXFileRemoteStorageOSSGetUploadAuthController::class);

    self::$FileStorageInstance = new DiscuzXFileRemoteOSSStorage($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey);
    
    parent::useService($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey);

    self::$FileStorageInstance = new DiscuzXFileRemoteOSSStorage($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey);
    self::$FilesModelInstance = new DiscuzXFilesModel();
  }
}
