<?php

namespace kernel\Controller\Main\Files;

class UpdateFileController extends FileBaseController
{
  public $body = [
    "belongsId" => "string",
    "belongsType" => "string",
    "accessControl" => "string"
  ];
  public function data($FileKey)
  {
    if (!$this->platform->verifyOperationAuthorization($FileKey, "write")) return $this->platform->return();

    if (!$this->platform->fileExist($FileKey)) return $this->response->error(404, 404, "文件不存在");

    return $this->platform->getFilesModel()->save($this->body->some(), $FileKey);
  }
}
