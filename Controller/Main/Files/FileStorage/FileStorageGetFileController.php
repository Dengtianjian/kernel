<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Controller\Main\Files\GetFileController;
use kernel\Foundation\Config;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageGetFileController extends GetFileController
{
  use FileStorageControllerTrait;

  public $serializes = [
    "fileKey" => "string",
    "path" => "string",
    "extension" => "string",
    "size" => "int",
    "relativePath" => "string",
    "ownerId" => "string",
    "width" => "double",
    "height" => "double"
  ];
  public function data($FileKey)
  {
    if (!$this->query->has("signature")) {
      return $this->response->error(403, 403, "无权操作");
    }

    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    return FileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, null, $URLParams, $Headers, $this->request->method);
  }
}
