<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\Driver\FileStorageDriver;
use kernel\Platform\DiscuzX\DiscuzXURL;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXFileStorageDriver extends FileStorageDriver
{

  public function __construct($VerifyAuth, $SignatureKey)
  {
    parent::__construct($VerifyAuth, $SignatureKey);

    $this->filesModel = new DiscuzXFilesModel();
  }
  public function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new DiscuzXURL(F_BASE_URL);

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "files/{$FileKey}/preview";

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  public function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new DiscuzXURL(F_BASE_URL);

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "files/{$FileKey}/download";

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
