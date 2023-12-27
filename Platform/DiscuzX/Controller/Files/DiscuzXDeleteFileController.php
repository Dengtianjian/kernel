<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Controller\Main\Files\DeleteFileController;
use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Service\DiscuzXFileStorageService;

class DiscuzXDeleteFileController extends DeleteFileController
{
  public $query = [
    "signature" => "string",
    "sign-algorithm" => "string",
    "sign-time" => "string",
    "key-time" => "string",
    "header-list" => "string",
    "url-param-list" => "string",
  ];

  public function data($FileKey)
  {
    if (!$this->query->has("signature")) {
      return $this->response->error(403, 403, "无权操作");
    }
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $HTTPMethod = $this->request->method;
    $Headers = $this->request->header->some();
    $URLParams = $this->request->query->some();
    unset($URLParams['id'], $URLParams['uri']);

    $authId = null;
    if (array_key_exists("authId", $URLParams)) {
      $authId = getglobal("uid");
    }

    return DiscuzXFileStorageService::deleteFile($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $authId, $HTTPMethod);
  }
}
