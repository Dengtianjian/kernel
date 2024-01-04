<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;
use kernel\Service\OSS\OSSService;
use kernel\Traits\FileStorageControllerTrait;

class DiscuzXFileStorageDownloadFileController extends DiscuzXController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $Params = $this->getParams();

    $File = DiscuzXFileStorageService::getFileInfo($FileKey, $Params['signature'], $Params['signatureKey'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
    if ($File->error) return $File;

    if ($File->getData("remote")) {
      return $this->response->redirect(OSSService::getAccessURL($FileKey, $this->getRequestParams(), 600, NULL, "get", true, true)->getData());
    }

    return $this->response->download($File->getData("fullPath"));
  }
}
