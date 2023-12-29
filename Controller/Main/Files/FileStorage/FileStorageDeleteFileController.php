<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Controller\Main\Files\DeleteFileController;
use kernel\Foundation\Config;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageDeleteFileController extends DeleteFileController
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
    unset($URLParams['id'], $URLParams['uri']);
    $Headers = $this->request->header->some();

    return FileStorageService::deleteFile($FileKey, $Signature, $SignatureKey, null, $URLParams, $Headers, $this->request->method);
  }
}
