<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXFileService;

class DiscuzXDeleteFileController extends DiscuzXController
{
  public function data($FileKey)
  {
    global $_G;

    if ($_G['adminid'] != 1) {
      return $this->response->error(403, 403, "抱歉，您没有权限删除该文件");
    }

    return DiscuzXFileService::deleteFile($FileKey);
  }
}
