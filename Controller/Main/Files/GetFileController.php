<?php

namespace kernel\Controller\Main\Files;

class GetFileController extends FileBaseController
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
    if (!$this->driver->verifyRequestAuth($FileKey)) {
      return $this->response->error(403, 403, "抱歉，您没有获取该文件信息的权限");
    }
    $GetResponse = $this->driver->getFileInfo($FileKey);
    if ($GetResponse->error) return $GetResponse;
    $FileInfo = $GetResponse->getData();
    if (!array_key_exists("fileKey", $FileInfo)) {
      $FileInfo['fileKey'] = $FileInfo['key'];
    }
    if (!array_key_exists("size", $FileInfo)) {
      $FileInfo['size'] = $FileInfo['fileKey'];
    }

    return $FileInfo;
  }
}
