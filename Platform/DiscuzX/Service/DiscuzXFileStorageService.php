<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files as DiscuzXFilesNamespace;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Service\FileStorageService;

class DiscuzXFileStorageService extends FileStorageService
{
  static function init()
  {
    DiscuzXFilesModel::singleton()->createTable();
  }
  static function useService()
  {
    Router::post("files", DiscuzXFilesNamespace\DiscuzXUploadFileController::class);
    Router::delete("files/{fileId:.+?}", DiscuzXFilesNamespace\DiscuzXDeleteFileController::class);
    Router::get("files/{fileId:.+?}/preview", DiscuzXFilesNamespace\DiscuzXAccessFileController::class);
    Router::get("files/{fileId:.+?}/download", DiscuzXFilesNamespace\DiscuzXDownloadFileController::class);
    Router::get("files/{fileId:.+?}", DiscuzXFilesNamespace\DiscuzXGetFileController::class);
  }
  static function getAccessURL($FileKey, $URLParams = [], $SignatureKey = NULL, $Expires = 600, $AuthId = null, $HTTPMethod = "get", $ACL = DiscuzXFileStorage::PRIVATE)
  {
    $accessURL = "";
    $R = new ReturnResult($accessURL);

    if ($SignatureKey) {
      $FileKeyInfo = pathinfo($FileKey);
      $accessURL = DiscuzXFileStorage::generateAccessURL($FileKeyInfo['dirname'], $FileKeyInfo['basename'], $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, $ACL);
    } else {
      $U = new URL(F_BASE_URL);
      $U->pathName = URL::combinedPathName("files", $FileKey);
      foreach ($URLParams as $key => $value) {
        $U->queryParam($value, $key);
      }
      return $U->toString();
    }

    return $R->success($accessURL);
  }
}
