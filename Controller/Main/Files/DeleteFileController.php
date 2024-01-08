<?php

namespace kernel\Controller\Main\Files;

class DeleteFileController extends FileBaseController
{
  public function data($FileKey)
  {
    if (!$this->driver->verifyRequestAuth($FileKey, TRUE)) {
      return $this->response->error(403, 403, "抱歉，您没有删除该文件的权限");
    }

    return $this->driver->deleteFile($FileKey);
  }
}
