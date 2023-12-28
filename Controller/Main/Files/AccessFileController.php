<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileService;

class AccessFileController extends AuthController
{
  /**
   * 主体
   *
   * @param string $FileKey 文件名
   * @return mixed
   */
  public function data($FileKey)
  {
    $File = FileService::getFileInfo($FileKey);
    if ($File->error) return $File;

    return $this->response->file($File->getData("fullPath"));
  }
}
