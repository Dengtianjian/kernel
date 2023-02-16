<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Platform\DiscuzX\DiscuzXFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class UploadFilesController extends DiscuzXController
{
  public function data()
  {
    if (count($_FILES) === 0 || !$_FILES['file']) {
      return new ResponseError(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    return DiscuzXFile::save($_FILES['file'], "files");
  }
}
