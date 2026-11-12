<?php

namespace kernel\Controllers\Main\Files;

use kernel\Modules\Storage\StorageModule;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\Arr;
use kernel\Modules\Storage\FilesModel;

class UploadFileController extends Controller
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
    $files = $this->request->file();
    if (!$files) {
      return $this->fail(400, "UploadFile:400001", "请上传文件", $_FILES);
    }

    if (!$storage->authorizeOperation($fileKey, "write", "upload")) {
      return $storage->return();
    }

    $uploadFile = Arr::first($files);

    $file = $storage->put($uploadFile, $fileKey);
    if ($storage->isError()) return $storage->return();

    if ($storage->dataSave()) {
      $fileData = $this->app()->make(FilesModel::class)->where("key", $fileKey)->first();
      if ($fileData) {
        $this->app()->make(FilesModel::class)->where("key", $fileKey)->update([
          "width" => $file->width,
          "height" => $file->height,
          "source_file_name" => $file->source_file_name,
          "size" => $file->size,
          "mime_type" => $file->mime_type,
        ]);

        $fileData['width'] = $file->width;
        $fileData['height'] = $file->height;
        $fileData['source_file_name'] = $file->source_file_name;
        $fileData['size'] = $file->size;
        $fileData['mime_type'] = $file->mime_type;
      } else {
        $storage->add($fileKey, $file->source_file_name, $file->name, $file->path, $file->size, $file->extension, $file->mime_type, $file->owner_id, $file->access_control, $file->disk, $file->ref, $file->type, $file->width, $file->height);

        $fileData =  $this->app()->make(FilesModel::class)->where("key", $fileKey)->first();
      }
      return $fileData;
    }

    return $file->toArray();
  }
}
