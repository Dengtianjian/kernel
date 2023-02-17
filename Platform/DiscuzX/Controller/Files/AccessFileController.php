<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Platform\DiscuzX\DiscuzXFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class AccessFileController extends Controller
{
  public function data(Request $R, $FileId)
  {
    $decodeData = DiscuzXFile::decodeFileId($FileId);
    if ($decodeData->error) {
      showmessage($decodeData->errorMessage());
    }
    return new ResponseFile($R, $decodeData->getData("filePath"));
  }
}
