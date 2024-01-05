<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Controller\Main\Files\AccessFileController;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageAccessFileController extends AccessFileController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $Params = $this->getParams();
    $File = FileStorageService::getFileInfo($FileKey, $Params['signature'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
    if ($File->error) return $File;

    return $this->response->file($File->getData("fullPath"));
  }
}
