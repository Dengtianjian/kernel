<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Controller\Main\Files\FileStorage\FileStorageDeleteFileController;
use kernel\Service\File\FileOSSStorageService;

class FileRemoteStorageOSSDeleteFileController extends FileStorageDeleteFileController
{
  public function data($FileKey)
  {
    $Params = $this->getParams();

    return FileOSSStorageService::deleteFile($FileKey, $Params['signature'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
