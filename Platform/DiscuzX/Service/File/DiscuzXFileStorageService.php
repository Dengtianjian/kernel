<?php

namespace kernel\Platform\DiscuzX\Service\File;

use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files\FileStorage as DiscuzXFileStorageNamespace;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileRemoteStorage;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFiles;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Service\File\FileStorageService;

class DiscuzXFileStorageService extends FileStorageService
{
  static function init()
  {
    DiscuzXFilesModel::singleton()->createTable();
  }
  static function useService($SignatureKey = null)
  {
    parent::useService($SignatureKey);

    // Router::post("fileStorage/upload/auth", DiscuzXFileStorageNamespace\DiscuzXFileStorageGetUploadFileAuthController::class);
    // Router::post("fileStorage/{fileId:.+?}", DiscuzXFileStorageNamespace\DiscuzXFileStorageUploadFileController::class);
    // Router::delete("fileStorage/{fileId:.+?}", DiscuzXFileStorageNamespace\DiscuzXFileStorageDeleteFileController::class);

    // Router::get("fileStorage/{fileId:.+?}/preview", DiscuzXFileStorageNamespace\DiscuzXFileStorageAccessFileController::class);
    // Router::get("fileStorage/{fileId:.+?}/download", DiscuzXFileStorageNamespace\DiscuzXFileStorageDownloadFileController::class);

    // Router::get("fileStorage/{fileId:.+?}", DiscuzXFileStorageNamespace\DiscuzXFileStorageGetFileController::class);

    self::$FileStorageInstance = new DiscuzXFileStorage($SignatureKey);
    self::$FilesModelInstance = new DiscuzXFilesModel();
    self::$FileRemoteStorageInstance = new DiscuzXFileRemoteStorage($SignatureKey);
    self::$Files = new DiscuzXFiles();
  }
}
