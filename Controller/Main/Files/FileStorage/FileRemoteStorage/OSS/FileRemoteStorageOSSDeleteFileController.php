<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Controller\Main\Files\FileStorage\FileStorageDeleteFileController;
use kernel\Foundation\Config;
use kernel\Service\OSS\OSSService;

class FileRemoteStorageOSSDeleteFileController extends FileStorageDeleteFileController
{
  public function data($FileKey)
  {
    $Params = $this->getParams();

    return OSSService::deleteFile($FileKey, $Params['signature'], $Params['signatureKey'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
