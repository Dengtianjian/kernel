<?php

namespace kernel\Controller\Main\Files;

class GetFileController extends FileBaseController
{
  public $serializes = [
    "key" => "string",
    "name" => "string",
    "extension" => "string",
    "size" => "int",
    "width" => "double",
    "height" => "double"
  ];
  public function data($FileKey)
  {
    $GetResponse = $this->driver->getFileInfo($FileKey);
    if ($this->driver->error) return $this->driver->return();

    return $GetResponse->toArray();
  }
}
