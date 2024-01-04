<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Controller\Main\Files\DeleteFileController;
use kernel\Foundation\Config;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageDeleteFileController extends DeleteFileController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $Params = $this->getParams();

    return FileStorageService::deleteFile($FileKey, $Params['signature'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
