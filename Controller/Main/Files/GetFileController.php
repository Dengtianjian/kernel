<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileService;

class GetFileController extends AuthController
{
  public $serializes = [
    "fileKey" => "string",
    "path" => "string",
    "fileName"=>"string",
    "extension" => "string",
    "size" => "int",
    "relativePath" => "string",
    "width" => "double",
    "height" => "double"
  ];
  public function data($FileKey)
  {
    return FileService::getFileInfo($FileKey);
  }
}
