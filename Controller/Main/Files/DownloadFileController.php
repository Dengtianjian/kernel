<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileService;

class DownloadFileController extends AuthController
{

  public function data($FileKey)
  {
    $File = FileService::getFileInfo($FileKey);
    if ($File->error) return $File;

    return $this->response->download($File->getData("fullPath"));
  }
}
