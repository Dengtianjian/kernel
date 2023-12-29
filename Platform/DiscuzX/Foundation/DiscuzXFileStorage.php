<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;

class DiscuzXFileStorage extends FileStorage
{
  static function generateAccessURL($FileKey, $URLParams = [], $SignatureKey = null, $Expires = 600,  $HTTPMethod = "get")
  {
    if ($SignatureKey) {
      $URLParams = array_merge($URLParams, self::generateAccessAuth($FileKey, $SignatureKey, $Expires, $URLParams, $HTTPMethod, false));
    }

    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";
    $AccessURL->pathName = "files/{$FileKey}";

    $URLParams['uri'] = "files/{$FileKey}";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
