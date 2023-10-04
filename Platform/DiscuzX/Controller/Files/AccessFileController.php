<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\File;
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
    $decodeData = $decodeData->getData();
    if (isset($decodeData['auth']) && $decodeData['auth']) {
      if (File::isImage($decodeData["filePath"])) {
        if ($_G['group']['allowgetimage'] == "0" && $_G['adminid'] != 1) {
          if ($decodeData['userId'] && $decodeData['userId'] != $_G['uid']) {
            showmessage("抱歉，您没有权限获取图片信息");
          }
        }
      } else if ($_G['group']['allowgetattach'] == "0") {
        if ($_G['adminid'] != 1) {
          if ($decodeData['userId'] && $decodeData['userId'] != $_G['uid']) {
            showmessage("抱歉，您没有权限获取附件信息");
          }
        }
      }
    }

    return new ResponseFile($this->request, $decodeData["filePath"]);
  }
}
