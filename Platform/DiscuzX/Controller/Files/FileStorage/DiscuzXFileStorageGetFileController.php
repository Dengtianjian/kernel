<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;
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
    "height" => "double",
    "remote" => "boolean"
  ];

  public function data($FileKey)
  {
    $Params = $this->getParams();
    return DiscuzXFileStorageService::getFileInfo($FileKey, $Params['signature'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
  }
}
