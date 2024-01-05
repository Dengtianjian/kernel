<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class DiscuzXFileStorageUploadFileController extends DiscuzXController
{
  use FileStorageControllerTrait;

  public function data($FileKey)
  {
    $Files = array_values($_FILES);
    if (!$Files) {
      return $this->response->error(400, "UploadFile:400001", "请上传文件", $_FILES);
    }
    $Body = $this->body->some();

    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    $VerifedResult = DiscuzXFileStorageService::verifyAccessAuth($FileKey, $Signature, $URLParams, $Headers, $this->request->method);
    if ($VerifedResult !== true) {
      return $this->response->error(403, 403, "签名错误", $VerifedResult);
    }

    $UploadedResult = DiscuzXFileStorageService::upload($Files[0], $FileKey, $Body['ownerId'], $Body['belongsId'], $Body['belongsType'], $Body['acl']);
    if ($UploadedResult->error) return $UploadedResult;

    return $FileKey;
  }
}
