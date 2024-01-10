<?php

namespace kernel\Controller\Main\Files;

class DownloadFileController extends FileBaseController
{
  public function data($FileKey)
  {
    $File = $this->driver->getFileInfo($FileKey);
    if ($this->driver->error) return $this->driver->return();

    if ($File->remote) {
      return $this->response->redirect($this->driver->getFileRemoteDownloadURL($FileKey, []), 302);
    } else {
      return $this->response->download($File->filePath);
    }
  }
}
