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

    /**
     *  "sourceFileName": "1.png",
        "fileName": "658e5581953f8.png",
        "filePath": "files",
        "fileKey": "files/658e5581953f8.png",
        "auth": "sign-algorithm=sha1&sign-time=1703826817;1703827417&key-time=1703826817;1703827417&header-list=&signature=78623674b35f4e98b2d3a95aca3af0a926e50ab2&url-param-list=acl&acl=private"
     */
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
    unset($URLParams['id'], $URLParams['uri']);

    $VerifedResult = FileStorageService::verifyAccessAuth($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $this->request->method);
    if ($VerifedResult !== true) {
      return $this->response->error(403, 403, "签名错误", $VerifedResult);
    }

    $UploadedResult = FileStorageService::upload($Files[0], $FileKey, $Body['ownerId'], $Body['belongsId'], $Body['belongsType'], $Body['acl']);
    if ($UploadedResult->error) return $UploadedResult;

    return $FileKey;
  }
}
