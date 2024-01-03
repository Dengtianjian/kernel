<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Service\File\DiscuzXFileStorageService;

class DiscuzXFileStorageGetUploadFileAuthController extends DiscuzXController
{
  public $body = [
    "fileName" => "string",
    "filePath" => "string",
    "rename" => "boolean"
  ];
  public function data()
  {
    $Body = $this->body->some();
    $SignatureKey = Config::get("signatureKey") ?: "";

    $fileName = $sourceFileName = $Body['fileName'];
    if (!$this->body->has("rename") || $Body['rename']) {
      $FileInfo = pathinfo($sourceFileName);
      $fileName = uniqid() . "." . $FileInfo['extension'];
    }
    $FileKey = DiscuzXFileStorage::combinedFileKey($Body['filePath'], $fileName);
    $Auth = DiscuzXFileStorageService::getAccessAuth($FileKey, $SignatureKey, 600, [], "post", true);
    if ($Auth->error) return $Auth;

    return [
      "sourceFileName" => $sourceFileName,
      "fileName" => $fileName,
      "filePath" => $Body['filePath'],
      "fileKey" => $FileKey,
      "auth" => $Auth->getData()
    ];
  }
}
