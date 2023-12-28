<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileService;
use kernel\Traits\FileControllerTrait;

class GetFileController extends AuthController
{
  use FileControllerTrait;

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

    return FileService::getFileInfo($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $AuthId);
  }
}
