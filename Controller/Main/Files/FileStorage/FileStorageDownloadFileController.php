<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Controller\Main\Files\DownloadFileController;
use kernel\Foundation\Config;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageDownloadFileController extends DownloadFileController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $Params = $this->getParams();

    $File = FileStorageService::getFileInfo($FileKey, $Params['signature'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
    if ($File->error) return $File;

    return $this->response->download($File->getData("fullPath"));
  }
}
