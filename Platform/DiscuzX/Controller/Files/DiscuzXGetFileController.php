<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXFileService;

class DiscuzXGetFileController extends DiscuzXController
{
  public $serializes = [
    "fileKey" => "string",
    "path" => "string",
    "fileName" => "string",
    "extension" => "string",
    "size" => "int",
    "relativePath" => "string",
    "width" => "double",
    "height" => "double"
  ];
  public function data($FileKey)
  {
    return DiscuzXFileService::getFileInfo($FileKey);
  }
}
