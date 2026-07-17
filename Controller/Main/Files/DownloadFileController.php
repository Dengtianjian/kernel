<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\File\FileHelper;

class DownloadFileController extends FileBaseController
{
  public function data($FileKey)
  {
    $File = $this->platform->getFile($FileKey);
    if ($this->platform->error) return $this->platform->return();

    if ($File->remote) {
      $URL = $this->platform->getFileDownloadURL($FileKey);
      if (!$URL) return $this->response->error(404, 404, "下载文件失败", "获取到的远程文件URL为空");

      return $this->response->redirect($URL, 302);
    } else {
      $FilePath = FileHelper::combinedFilePath(F_APP_STORAGE, $File->filePath);
      if (!file_exists($FilePath)) {
        return $this->response->error(404, 404, "文件不存在", "文件实体不存在");
      }
      return $this->response->download($FilePath);
    }
  }
}
