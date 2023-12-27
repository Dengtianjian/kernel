<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Controller\Main\Files\DownloadFileController;
use kernel\Foundation\Config;
use kernel\Service\FileStorageService;

class DiscuzXDownloadFileController extends DownloadFileController
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
    $Headers = $this->request->header->some();
    $URLParams = $this->request->query->some();
    unset($URLParams['id'], $URLParams['uri']);

    $authId = null;
    if (array_key_exists("authId", $URLParams)) {
      $authId = getglobal("uid");
    }

    $File = FileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $authId);
    if ($File->error) return $File;

    return $this->response->download($File->getData("fullPath"));
  }
}
