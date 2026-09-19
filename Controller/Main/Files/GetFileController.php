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
    "height" => "double",
    "url" => "string",
    "previewURL" => "string",
    "downloadURL" => "string",
    "transferPreviewURL"=>"string",
    "transferDownloadURL"=>"string"
  ];
  public function data($FileKey)
  {
    $GetResponse = $this->platform->getFile($FileKey);
    if ($this->platform->error) return $this->platform->return();

    return $GetResponse->toArray();
  }
}
