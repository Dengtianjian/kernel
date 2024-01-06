<?php

namespace kernel\Controller\Main\Files\FilesRemote;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FilesRemoteService;

class FilesRemoteDownloadFileController extends AuthController
{

  public function data($FileKey)
  {
    return $this->response->redirect(FilesRemoteService::getFileDownloadURL($FileKey), 302);
  }
}
