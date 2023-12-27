<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Service\FileStorageService;

class GetFileController extends Controller
{
  public $query = [
    "signature" => "string",
    "sign-algorithm" => "string",
    "sign-time" => "string",
    "key-time" => "string",
    "header-list" => "string",
    "url-param-list" => "string"
  ];

  public function data($FileKey)
  {
    if (!$this->query->has("signature")) {
      return $this->response->error(403, 403, "无权操作");
    }

    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    $AuthId = $this->query->get("authId");
    unset($URLParams['id'], $URLParams['uri']);

    return FileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $AuthId);
  }
}