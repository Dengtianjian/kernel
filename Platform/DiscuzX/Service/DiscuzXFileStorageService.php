<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Service\FileStorageService;

class DiscuzXFileStorageService extends FileStorageService
{
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
