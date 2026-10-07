<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\FileSystem\Storage\StorageFile;

class GetFileController extends Controller
{
  public $responseSerializes = [
    "id" => "int",
    "key" => "string",
    "name" => "string",
    "source_file_name" => "string",
    "path" => "string",
    "extension" => "string",
    "size" => "int",
    "width" => "int",
    "height" => "int",
    // "disk" => "string",
    "mime_type" => "string"
  ];
  public function data($fileKey)
  {
    if (!Storage::authorizeOperation($fileKey)) return Storage::return();

    if (Storage::dataSave()) {
      $file = Storage::model()->where("key", $fileKey)->first();
    } else {
      $file = Storage::get($fileKey);
      if (Storage::isError()) return Storage::return();
    }

    if (!$file) {
      return $this->fail(404, 404, "文件不存在");
    }

    return (new StorageFile($file))->toArray();
  }
}
