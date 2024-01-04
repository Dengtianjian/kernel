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
    $Params = $this->getParams();

    return FileStorageService::getFileInfo($FileKey, $Params['signature'], $Params['signatureKey'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
