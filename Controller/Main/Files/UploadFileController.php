<?php

namespace kernel\Controller\Main\Files;

class UploadFileController extends FileBaseController
{
  public function data($FileKey)
  {
    if (!$this->driver->verifyRequestAuth($FileKey, TRUE)) {
      return $this->response->error(403, 403, "抱歉，您没有上传文件的权限");
    }

    $Files = array_values($_FILES);
    if (!$Files) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    return $this->driver->uploadFile($Files[0], $FileKey);
  }
}
