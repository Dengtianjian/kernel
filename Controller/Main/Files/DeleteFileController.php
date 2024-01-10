<?php

namespace kernel\Controller\Main\Files;

class DeleteFileController extends FileBaseController
{
  public function data($FileKey)
  {
    $DeletedResult = $this->driver->deleteFile($FileKey);
    if ($this->driver->error) return $this->driver->return();

    return $DeletedResult;
  }
}
