<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Controller\Main\Files\FileStorage\FileStorageGetFileController;
use kernel\Foundation\Config;
use kernel\Service\File\FileOSSStorageService;

class FileRemoteStorageOSSGetFileController extends FileStorageGetFileController
{
  public function data($FileKey)
  {
    $Params = $this->getParams();

    return FileOSSStorageService::getFileInfo($FileKey, $Params['signature'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
