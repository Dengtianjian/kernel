<?php

namespace kernel\Controller\Main\Files;

class PreviewFileController extends FileBaseController
{
  /**
   * 主体
   *
   * @param string $FileKey 文件名
   * @return mixed
   */
  public function data($FileKey)
  {
    $File = $this->driver->getFileInfo($FileKey);
    if ($this->driver->error) return $this->driver->return();
    if ($File->remote) {
      return $this->response->redirect($this->driver->getFileRemotePreviewURL($FileKey, []), 302);
    } else {
      return $this->response->file($File->filePath);
    }
  }
}
