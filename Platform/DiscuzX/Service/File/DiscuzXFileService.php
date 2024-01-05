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
  }
  static function getAccessURL($FileKey, $URLParams = [])
  {
    $R = new ReturnResult(null);

    $FileInfo = pathinfo($FileKey);
    $AccessURL = DiscuzXFiles::generateAccessURL($FileInfo['dirname'], $FileInfo['filename'], $URLParams);

    return $R->success($AccessURL);
  }
  static function getDownloadURL($FileKey, $URLParams = [])
  {
    return self::getAccessURL($FileKey, $URLParams);
  }
}
