<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileService;

class DeleteFileController extends AuthController
{
  public function data($fileKey)
  {
    return FileService::deleteFile($fileKey);
  }
}
