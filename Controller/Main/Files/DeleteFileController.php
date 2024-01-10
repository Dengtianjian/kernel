<?php

namespace kernel\Controller\Main\Files;

class DeleteFileController extends FileBaseController
{
  public function data($FileKey)
  {
    return $this->driver->deleteFile($FileKey);
  }
}
