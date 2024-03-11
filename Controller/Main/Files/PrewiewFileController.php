<?php

namespace kernel\Controller\Main\Files;

class PrewiewFileController extends FileBaseController
{
  /**
   * 主体
   *
   * @param string $FileKey 文件名
   * @return mixed
   */
  public function data($FileKey)
  {
    $File = $this->driver->getFileInfo($FileKey, FALSE);
    if ($this->driver->error) return $this->driver->return();

    if ($File->remote) {
      $URL = $this->driver->getFileRemotePreviewURL($FileKey, $this->driver->transformToRemoteURLParams($this->request->query->some()));
      if (!$URL) return $this->response->error(500, 500, "预览文件失败", "获取到的远程文件URL为空");

      return $this->response->redirect($URL, 302);
    } else {
      return $this->response->file($File->filePath);
    }
  }
}
