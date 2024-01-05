<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\Files;
use kernel\Foundation\HTTP\URL;

class DiscuzXFiles extends Files
{
  static function generateAccessURL($FilePath, $FileName, $URLParams = [])
  {
    $FileKey = rawurlencode(self::combinedFileKey($FilePath, $FileName));

    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "files/{$FileKey}/preview";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
