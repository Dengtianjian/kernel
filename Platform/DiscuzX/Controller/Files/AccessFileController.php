<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Platform\DiscuzX\DiscuzXFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class AccessFileController extends Controller
{
  public function data($FileId)
  {
    $decodeData = DiscuzXFile::decodeFileId($FileId);
    if ($decodeData->error) {
      showmessage($decodeData->errorMessage());
    }
    global $_G;
    if ($_G['group']['allowgetattach'] == "0" || ($decodeData['userId'] != getglobal("uid") || getglobal("adminid") != 1)) {
      return $this->response->error(403, 403, "抱歉，您没有权限获取附件信息");
    }
    return new ResponseFile($this->request, $decodeData->getData("filePath"));
  }
}
