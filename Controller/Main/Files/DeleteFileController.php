<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileService;
use kernel\Traits\FileControllerTrait;

class DeleteFileController extends AuthController
{
  use FileControllerTrait;

  public function data($fileKey)
  {
    if (!$this->query->has("signature")) {
      return $this->response->error(403, 403, "无权操作");
    }
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $HTTPMethod = $this->request->method;
    $URLParams = $this->request->query->some();
    unset($URLParams['id'], $URLParams['uri']);
    $Headers = $this->request->header->some();
    $AuthId = $this->query->get("authId");

    return FileService::deleteFile($fileKey, $Signature, $SignatureKey, $URLParams, $Headers, $AuthId, $HTTPMethod);
  }
}
