<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Controller\Main\Files\UploadFilesController;
use kernel\Platform\DiscuzX\Service\DiscuzXFileStorageService;

class DiscuzXUploadFileController extends UploadFilesController
{
  public function data()
  {
    global $_G;

    if (count($_FILES) === 0 || !$_FILES['file']) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    if ($_G['group']['allowpostattach'] == "0") {
      return $this->response->error(403, 403, "抱歉，您目前没有权限上传附件");
    }
    $File = $_FILES['file'];
    if ($_G['group']['maxattachsize'] != 0 && $File['size'] > $_G['group']['maxattachsize']) {
      return $this->response->error(400, 400, "单个文件大小不得超过" . round(($_G['group']['maxattachsize'] / 1024 / 1024)) . "MB");
    }
    if ($_G['group']['attachextensions']) {
      $extension = explode("/", $File['type'])[1];
      if (strpos($_G['group']['attachextensions'], $extension) === false) {
        return $this->response->error(400, 400, "抱歉，您只可以上传以下 " . $_G['group']['attachextensions'] . " 类型的附件");
      }
    }
    $UploadedResult = DiscuzXFileStorageService::upload($File, "files");
    if ($UploadedResult->error) return $UploadedResult;

    return $UploadedResult->getData("fileKey");
  }
}
