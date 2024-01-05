<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFiles;
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

    $fileName = $sourceFileName = $Body['fileName'];
    if (!$this->body->has("rename") || $Body['rename']) {
      $FileInfo = pathinfo($sourceFileName);
      $fileName = uniqid() . "." . $FileInfo['extension'];
    }
    $FileKey = DiscuzXFiles::combinedFileKey($Body['filePath'], $fileName);
    $Auth = DiscuzXFileStorageService::getAccessAuth($FileKey, 600, [], [], "post", true);
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
