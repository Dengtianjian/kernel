<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Controller\Main\Files\AccessFileController;
use kernel\Foundation\Config;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageAccessFileController extends AccessFileController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    $File = FileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, null, $URLParams, $Headers, $this->request->method);
    if ($File->error) return $File;

    return $this->response->file($File->getData("fullPath"));
  }
}