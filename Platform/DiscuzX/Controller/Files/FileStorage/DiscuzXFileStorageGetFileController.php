<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXFileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class DiscuzXFileStorageGetFileController extends DiscuzXController
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
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    return DiscuzXFileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, null, $URLParams, $Headers, $this->request->method);
  }
}
