<?php

namespace kernel\Controller\Main\Files\FilesRemote;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FilesRemoteService;

class FilesRemoteAccessFileController extends AuthController
{
  /**
   * 主体
   *
   * @param string $FileKey 文件名
   * @return mixed
   */
  public function data($FileKey)
  {
    return $this->response->redirect(FilesRemoteService::getFilePreviewURL($FileKey), 302);
  }
}
