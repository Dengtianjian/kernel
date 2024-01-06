<?php

namespace kernel\Platform\DiscuzX\Service\File;

use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Service\File\FileService;
use kernel\Platform\DiscuzX\Controller\Files as DiscuzXFilesNamespace;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFiles;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;

class DiscuzXFileService extends FileService
{
  /**
   * 使用服务
   *
   * @return void
   */
  static function useService()
  {
    Router::post("files", DiscuzXFilesNamespace\DiscuzXUploadFileController::class);
    Router::delete("files/{fileId:.+?}", DiscuzXFilesNamespace\DiscuzXDeleteFileController::class);
    Router::get("files/{fileKey:.+?}/preview", DiscuzXFilesNamespace\DiscuzXAccessFileController::class);
    Router::get("files/{fileId:.+?}/download", DiscuzXFilesNamespace\DiscuzXDownloadFileController::class);
    Router::get("files/{fileId:.+?}", DiscuzXFilesNamespace\DiscuzXGetFileController::class);
    Router::get("files/remote/upload/auth", DiscuzXFilesNamespace\DiscuzXGetUploadRemoteAuthController::class);

    self::$Files = DiscuzXFiles::class;
  }
}
