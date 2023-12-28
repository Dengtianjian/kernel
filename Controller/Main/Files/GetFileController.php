<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileService;

class GetFileController extends AuthController
{
  public function data($FileKey)
  {
    return FileService::getFileInfo($FileKey);
  }
}
