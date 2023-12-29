<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Foundation\Config;
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

    if (!$this->query->has("signature")) {
      return $this->response->error(403, 403, "无权操作");
    }

    $Body = $this->body->some();

    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    $AuthId = $this->query->get("authId");
    unset($URLParams['id'], $URLParams['uri']);

    $VerifedResult = FileStorageService::verifyAccessAuth($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $AuthId, "post");
    if ($VerifedResult !== true) {
      return $this->response->error(403, 403, "签名错误", $VerifedResult);
    }

    $UploadedResult = FileStorageService::upload($Files[0], $FileKey, $Body['saveFileName'], $Body['ownerId'], $Body['belongsId'], $Body['belongsType'], $Body['acl']);
    if ($UploadedResult->error) return $UploadedResult;

    return $FileKey;
  }
}
