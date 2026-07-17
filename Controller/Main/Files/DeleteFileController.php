<?php

namespace kernel\Controller\Main\Files;

class DeleteFileController extends FileBaseController
{
  public function data($FileKey)
  {
    $DeletedResult = $this->platform->deleteFile($FileKey);
    if ($this->platform->error) return $this->platform->return();

    return $DeletedResult;
  }
}
