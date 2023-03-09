<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\HTTP\Request;
use kernel\Platform\DiscuzX\DiscuzXFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class DeleteFileController extends DiscuzXController
{
  public function data($fileId)
  {
    return DiscuzXFile::deleteFile($fileId);
  }
}
