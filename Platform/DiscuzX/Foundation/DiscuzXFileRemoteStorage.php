<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXFileRemoteStorage extends FileStorage
{
  function __construct($SignatureKey)
  {
    parent::__construct($SignatureKey);
    $this->filesModel = new DiscuzXFilesModel();
  }
  function generateAccessURL($FileKey, $URLParams = [], $Headers = [], $WithSignature = true, $Expires = 600, $HTTPMethod = "get")
  {
    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->generateAccessAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, false));
    }

    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";

    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "files/{$FileKey}/preview";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
