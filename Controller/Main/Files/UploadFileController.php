<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\Arr;

class UploadFileController extends Controller
{
  public function data(string $fileKey)
  {
    $files = $this->request->file();
    if (!$files) {
      return $this->fail(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    $uploadFile = Arr::first($files);

    $file = Storage::put($uploadFile, $fileKey);
    if (Storage::isError()) return Storage::return();

    if (Storage::dataSave()) {
      $fileData = Storage::model()->where("key", $fileKey)->first();
      if ($fileData) {
        Storage::model()->where("key", $fileKey)->update([
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
        Storage::add($fileKey, $file->source_file_name, $file->name, $file->path, $file->size, $file->extension, $file->mime_type, $file->owner_id, $file->access_control, $file->disk, $file->ref, $file->type, $file->width, $file->height);

        $fileData = Storage::model()->where("key", $fileKey)->first();
      }
      return $fileData;
    }

    return $file->toArray();
  }
}
