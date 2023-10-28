<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Store;
use kernel\Service\FileStoreService;
use superApp\Foundation\SuperAppAuthController;

class UploadFilesController extends SuperAppAuthController
{
  public $query = [
    "auth" => "boolean"
  ];
  public function data()
  {
    if (!Store::getApp("userId") || !Store::getApp("user")['admin']) {
      return $this->response->error(403, 403, "抱歉，您没有权限上传附件");
    }

    $File = $_FILES['file'];
    if (count($_FILES) === 0 || !$_FILES['file']) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    return FileStoreService::save($_FILES['file'], "files", null, $this->query->has("auth") ? $this->query->get("auth") : true);
  }
}
