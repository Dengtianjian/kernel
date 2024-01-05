<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageUploadFileController extends AuthController
{
  use FileStorageControllerTrait;

  public $body = [
    "saveFileName" => "string",
    "ownerId" => "string",
    "belongsId" => "string",
    "belongsType" => "string",
    "acl" => "string"
  ];
  public function data($FileKey)
  {
    $Files = array_values($_FILES);
    if (!$Files) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    $Body = $this->body->some();

    $Params = $this->getParams();

    $VerifedResult = FileStorageService::verifyAccessAuth($FileKey, $Params['signature'], $Params['URLParams'], $Params['headers'], $this->request->method);
    if ($VerifedResult !== true) {
      return $this->response->error(403, 403, "签名错误", $VerifedResult);
    }

    $UploadedResult = FileStorageService::upload($Files[0], $FileKey, $Body['ownerId'], $Body['belongsId'], $Body['belongsType'], $Body['acl']);
    if ($UploadedResult->error) return $UploadedResult;

    return $FileKey;
  }
}
