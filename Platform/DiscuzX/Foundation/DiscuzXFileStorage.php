<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;

class DiscuzXFileStorage extends FileStorage
{
  function generateAccessURL($FileKey, $URLParams = [], $Headers = [], $WithSignature = true, $Expires = 600, $HTTPMethod = "get")
  {
    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->generateAccessAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, false));
    }

    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";

    $URLParams['uri'] = "files/{$FileKey}";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
