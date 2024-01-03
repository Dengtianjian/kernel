<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class DiscuzXFileStorageDeleteFileController extends DiscuzXController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    unset($URLParams['id'], $URLParams['uri']);
    $Headers = $this->request->header->some();

    return DiscuzXFileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, null, $URLParams, $Headers, $this->request->method);
  }
}
