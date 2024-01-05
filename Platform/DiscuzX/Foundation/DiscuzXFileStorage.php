<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXFileStorage extends FileStorage
{
  function __construct($SignatureKey)
  {
    parent::__construct($SignatureKey);
    $this->filesModel = new DiscuzXFilesModel();
  }

  function getFilePreviewURL($FileKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true)
  {
    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, $Headers, "get", false));
    }

    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";

    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "fileStorage/{$FileKey}/preview";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  function getFileDownloadURL($FileKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true)
  {
    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, $Headers, "get", false));
    }

    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "plugin.php";

    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "fileStorage/{$FileKey}/download";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
