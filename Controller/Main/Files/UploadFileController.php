<?php

namespace kernel\Controller\Main\Files;

class UploadFileController extends FileBaseController
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
    "transferPreviewURL" => "string",
    "transferDownloadURL" => "string"
  ];
  public function data($FileKey)
  {
    $Files = array_values($_FILES);
    if (!$Files) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }

    $FileInfo = $this->platform->uploadFile($Files[0], $FileKey);
    if ($this->platform->error) return $this->platform->return();

    return $FileInfo->toArray();
  }
}
