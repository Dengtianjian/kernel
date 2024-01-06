<?php

namespace kernel\Controller\Main\Files\FilesRemote;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FilesRemoteService;

class FilesRemoteGetFileController extends AuthController
{
  public $serializes = [
    "fileKey" => "string",
    "fileName" => "string",
    "extension" => "string",
    "size" => "int",
    "width" => "double",
    "height" => "double"
  ];
  public function data($FileKey)
  {
    return FilesRemoteService::getFileInfo($FileKey);
  }
}
