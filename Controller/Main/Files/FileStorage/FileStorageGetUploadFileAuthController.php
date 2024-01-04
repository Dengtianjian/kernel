<?php

namespace kernel\Controller\Main\Files\FileStorage;

use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\File\Files;
use kernel\Service\File\FileStorageService;
use kernel\Traits\FileStorageControllerTrait;

class FileStorageGetUploadFileAuthController extends AuthController
{
  use FileStorageControllerTrait;

  public $body = [
    "fileName" => "string",
    "filePath" => "string",
    "rename" => "boolean"
  ];
  public function data()
  {
    $Body = $this->body->some();

    $fileName = $sourceFileName = $Body['fileName'];
    if (!$this->body->has("rename") || $Body['rename']) {
      $FileInfo = pathinfo($sourceFileName);
      $fileName = uniqid() . "." . $FileInfo['extension'];
    }
    $FileKey = Files::combinedFileKey($Body['filePath'], $fileName);
    $Auth = FileStorageService::getAccessAuth($FileKey, 600, [], "post", true);
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
