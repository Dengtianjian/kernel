<?php

namespace kernel\Controller\Main\Files;

class UploadFileController extends FileBaseController
{
  // public $body = [
  //   "accessControl" => "string",
  //   "ownerId" => "string",
  //   "belongsId" => "string",
  //   "belongsType" => "string"
  // ];
  public $serializes = [
    "key" => "string",
    "name" => "string",
    "extension" => "string",
    "size" => "int",
    "width" => "double",
    "height" => "double",
    "url" => "string",
    "previewURL" => "string",
    "downloadURL" => "string"
  ];
  public function data($FileKey)
  {
    $Files = array_values($_FILES);
    if (!$Files) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    $FileInfo = $this->driver->uploadFile($Files[0], $FileKey);
    if ($this->driver->error) return $this->driver->return();

    return $FileInfo->toArray();
  }
}
