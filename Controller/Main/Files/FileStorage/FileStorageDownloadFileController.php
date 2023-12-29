<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Controller\Main\Files\DownloadFileController;
use kernel\Foundation\Config;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageDownloadFileController extends DownloadFileController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    if (!$this->query->has("signature")) {
      return $this->response->error(403, 403, "无权操作");
    }

    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    $AuthId = $this->query->get("authId");
    unset($URLParams['id'], $URLParams['uri']);

    $File = FileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $AuthId, $this->request->method);
    if ($File->error) return $File;

    return $this->response->download($File->getData("fullPath"));
  }
}
