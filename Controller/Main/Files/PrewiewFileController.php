<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\File\FileHelper;

class PrewiewFileController extends FileBaseController
{
  /**
   * 主体
   *
   * @param string $FileKey 文件名
   * @return mixed
   */
  public function data($fileKey = null)
  {
    $File = $this->platform->getFile($fileKey, FALSE);
    if (!$File) return $this->platform->return();

    if ($File->remote) {
      // $URL = $this->platform->getFilePreviewURL($fileKey, $this->platform->transformToRemoteURLParams($this->request->query->some()));
      $URL = $this->platform->getFilePreviewURL($fileKey);
      if (!$URL) return $this->response->error(404, 404, "预览文件失败", "获取到的远程文件URL为空");

      return $this->response->redirect($URL, 302);
    } else {
      $FilePath = FileHelper::combinedFilePath(F_APP_STORAGE, $File->filePath);
      if (!file_exists($FilePath)) {
        return $this->response->error(404, 404, "文件不存在", "文件实体不存在");
      }
      return $this->response->file($FilePath);
    }
  }
}
