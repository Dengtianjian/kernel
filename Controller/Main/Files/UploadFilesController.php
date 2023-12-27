<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\File\FileHelper;
use kernel\Service\FileStorageService;

class UploadFilesController extends Controller
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
