<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;
use kernel\Platform\DiscuzX\Service\File\DiscuzXOSSService;
use kernel\Traits\FileStorageControllerTrait;

class DiscuzXFileStorageDownloadFileController extends DiscuzXController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $Params = $this->getParams();

    $File = DiscuzXFileStorageService::getFileInfo($FileKey, $Params['signature'], getglobal("uid"), $Params['URLParams'], $Params['headers'], $this->request->method);
    if ($File->error) return $File;

    if ($File->getData("remote")) {
      return $this->response->redirect(DiscuzXOSSService::getAccessURL($FileKey, $this->getRequestParams(), [], true, 600, "get")->getData());
    }

    return $this->response->download($File->getData("fullPath"));
  }
}
