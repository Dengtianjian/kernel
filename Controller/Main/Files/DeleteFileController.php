<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Service\FileStorageService;

class DeleteFileController extends Controller
{
  public $query = [
    "signature" => "string",
    "sign-algorithm"=>"string",
    "sign-time"=>"string",
    "key-time"=>"string",
    "header-list"=>"string",
    "url-param-list"=>"string",
  ];
  public function data($fileKey)
  {
    if (!$this->query->has("signature")) {
      return $this->response->error(403, 403, "无权操作");
    }
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $HTTPMethod = $this->request->method;
    $URLParams = $this->query->some();
    $Headers = $this->request->header->some();
    $AuthId = $this->query->get("authId");
    return FileStorageService::deleteFile($fileKey, $Signature, $SignatureKey, $URLParams, $Headers, $AuthId, $HTTPMethod);
  }
}
