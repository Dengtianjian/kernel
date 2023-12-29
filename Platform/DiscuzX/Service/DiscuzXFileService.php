<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\Router;
use kernel\Service\File\FileService;
use kernel\Platform\DiscuzX\Controller\Files as DiscuzXFilesNamespace;

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
  }
}
