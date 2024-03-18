<?php

namespace kernel\Controller\Main\Files;

class DownloadFileController extends FileBaseController
{
  public function data($FileKey)
  {
    $File = $this->driver->getFileInfo($FileKey);
    if ($this->driver->error) return $this->driver->return();

    if ($File->remote) {
      $URL = $this->driver->getFileRemotePreviewURL($FileKey, $this->driver->transformToRemoteURLParams($this->request->query->some()));
      if (!$URL) return $this->response->error(404, 404, "下载文件失败", "获取到的远程文件URL为空");

      return $this->response->redirect($URL, 302);
    } else {
      return $this->response->download($File->filePath);
    }
  }
}
