<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class DiscuzXFileStorageDeleteFileController extends DiscuzXController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $Params = $this->getParams();
    return DiscuzXFileStorageService::deleteFile($FileKey, $Params['signature'], getglobal("uid"), $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
