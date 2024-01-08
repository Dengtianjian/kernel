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
    if (!$this->driver->verifyRequestAuth($FileKey, FALSE)) {
      return $this->response->error(403, 403, "抱歉，您没有预览该文件的权限");
    }

    $File = $this->driver->getFileInfo($FileKey);
    if ($File->error) return $File;

    if ($File->getData("remote")) {
      return $this->response->redirect($this->driver->getFileRemotePreviewURL($FileKey, []), 302);
    } else {
      return $this->response->file($File->getData("filePath"));
    }
  }
}
