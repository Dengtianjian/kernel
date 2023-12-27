<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\FileStorage;

class DiscuzXFileStorage extends FileStorage
{
  static function generateAccessURL($FilePath, $FileName, $SignatureKey = null, $Expires = 600, $URLParams = [], $AuthId = null, $HTTPMethod = "get", $ACL = self::PRIVATE)
  {
    $FileKey = rawurlencode(self::combinedFileKey($FilePath, $FileName));
    // $FileKey = self::combinedFileKey($FilePath, $FileName);
    $queryString = "";
    if ($SignatureKey) {
      $queryString = "?" . self::generateAccessAuth($FilePath, $FileName, $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, $ACL);
    }

    return F_BASE_URL . "plugin.php?id=" . F_APP_ID . "&uri=files/{$FileKey}{$queryString}";
  }
}
