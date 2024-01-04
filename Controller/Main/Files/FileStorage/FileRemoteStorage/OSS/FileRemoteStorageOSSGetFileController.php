<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Controller\Main\Files\FileStorage\FileStorageGetFileController;
use kernel\Foundation\Config;
use kernel\Service\OSS\OSSService;

class FileRemoteStorageOSSGetFileController extends FileStorageGetFileController
{
  public function data($FileKey)
  {
    $Params = $this->getParams();

    return OSSService::getFileInfo($FileKey, $Params['signature'], $Params['signatureKey'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
