<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;
use kernel\Platform\DiscuzX\Service\File\DiscuzXOSSService;
use kernel\Traits\FileStorageControllerTrait;

class DiscuzXFileStorageAccessFileController extends DiscuzXController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    $File = DiscuzXFileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, null, $URLParams, $Headers, $this->request->method);
    if ($File->error) return $File;

    if ($File->getData('remote')) {
      return $this->response->redirect(DiscuzXOSSService::getAccessURL($FileKey)->getData(), 302);
    }

    return $this->response->file($File->getData("fullPath"));
  }
}
