<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Service\File\FileService;

class DiscuzXDownloadFileController extends DiscuzXController
{
  public function data($FileKey)
  {
    global $_G;

    if ($_G['adminid'] != 1) {
      if (!$_G['group']['allowgetattach']) {
        showmessage("抱歉，您所在的用户组无法下载文件");
      }
    }

    $File = FileService::getFileInfo($FileKey);
    if ($File->error) return $File;

    return $this->response->download($File->getData("fullPath"));
  }
}
