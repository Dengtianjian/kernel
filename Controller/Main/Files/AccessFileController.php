<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Service\FileStorageService;
use kernel\Traits\FileControllerTrait;

class AccessFileController extends AuthController
{
  use FileControllerTrait;

  /**
   * 主体
   *
   * @param string $FileKey 文件名
   * @return mixed
   */
  public function data($FileKey)
  {
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    $AuthId = $this->query->get("authId");
    unset($URLParams['id'], $URLParams['uri']);

    $File = FileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $AuthId);
    if ($File->error) return $File;

    return new ResponseFile($this->request, $File->getData("fullPath"));
  }
}
