<?php

namespace kernel\Controllers\Main\Files;

use kernel\Modules\Storage\StorageModule;
use kernel\Foundation\Controller\Controller;
use kernel\Modules\Storage\StorageFile;
use kernel\Modules\Storage\FilesModel;

class GetFileController extends Controller
{
  public $responseSerializes = [
    "id" => "int",
    "key" => "string",
    "name" => "string",
    "source_file_name" => "string",
    // "path" => "string",
    "extension" => "string",
    "size" => "int",
    "width" => "int",
    "height" => "int",
    // "disk" => "string",
    "mime_type" => "string"
  ];
  public function data(string $fileKey)
  {
    $storage = $this->app()->make(StorageModule::class);
    if (!$storage->authorizeOperation($fileKey, "read", "info")) return $storage->return();

    if ($storage->dataSave()) {
      $file = $this->app()->make(FilesModel::class)->where("key", $fileKey)->first();
    } else {
      $file = $storage->get($fileKey);
      if ($storage->isError()) return $storage->return();
    }

    if (!$file) {
      return $this->fail(404, 404, "文件不存在");
    }

    return (new StorageFile($file))->toArray();
  }
}
