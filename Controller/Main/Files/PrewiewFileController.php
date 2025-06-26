<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\File\FileHelper;
use kernel\Service\StorageService;

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

    $RequestQuerys = $this->request->query->some();
    $URLParams = [];
    foreach ($RequestQuerys as $key => $value) {
      if (!in_array($key, ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"])) {
        $URLParams[$key] = $value;
      }
    }

    $Platform = StorageService::getPlatform($File->platform);

    if ($File->remote && $File->platform !== "local") {
      if (!StorageService::hasPlatform($File->platform)) {
        return $this->response->error(400, 400, "抱歉，当前文件无法预览", "文件所属存储平台未实例化");
      }

      $URL = $Platform->getFilePreviewURL($fileKey,  $Platform->convertURLParams($URLParams, $File->platform));
      if (!$URL) return $this->response->error(404, 404, "预览文件失败", "获取到的远程文件URL为空");

      return $this->response->redirect($URL, 302);
    } else {
      $FilePath = FileHelper::combinedFilePath(F_APP_STORAGE, $File->filePath);
      if (!file_exists($FilePath)) {
        return $this->response->error(404, 404, "文件不存在", "文件实体不存在");
      }

      return $this->response->file($FilePath, $File->fileName, null, "max-age=43200");
    }
  }
}
