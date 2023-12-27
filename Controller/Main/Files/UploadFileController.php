<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\FileStorageService;

class UploadFileController extends AuthController
{
  public function data()
  {
    if (count($_FILES) === 0 || !$_FILES['file']) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    $UploadedResult = FileStorageService::upload($_FILES['file'], "Files");
    if ($UploadedResult->error) return $UploadedResult;

    return $UploadedResult->getData("fileKey");
  }
}
