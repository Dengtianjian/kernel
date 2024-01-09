<?php

namespace kernel\Controller\Main\Files;

class DownloadFileController extends FileBaseController
{
  public function data($FileKey)
  {
    if (!$this->driver->verifyRequestAuth($FileKey, TRUE)) {
      return $this->response->error(403, 403, "抱歉，您没有下载该文件的权限");
    }

    $File = $this->driver->getFileInfo($FileKey);
    if ($File->error) return $File;

    if ($File->getData("remote")) {
      return $this->response->redirect($this->driver->getFileRemoteDownloadURL($FileKey, []), 302);
    } else {
      return $this->response->download($File->getData('filePath'));
    }
  }
}
