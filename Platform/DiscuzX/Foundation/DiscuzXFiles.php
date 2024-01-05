<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\Files;
use kernel\Foundation\HTTP\URL;

class DiscuzXFiles extends Files
{
  static function getFilePreviewURL($FileKey, $URLParams = [])
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "files/{$FileKey}/preview";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  static function getFileDownloadURL($FileKey, $URLParams = [])
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "files/{$FileKey}/download";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
