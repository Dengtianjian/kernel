<?php

namespace kernel\Controller\Main\Files\FilesRemote;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FilesRemoteService;

class FilesRemoteDeleteFileController extends AuthController
{
  public function data($fileKey)
  {
    return FilesRemoteService::deleteFile($fileKey);
  }
}
