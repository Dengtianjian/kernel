<?php

namespace kernel\Platform\DiscuzX\Controller\Files\FileStorage;

use kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS\FileRemoteStorageOSSGetUploadAuthController;
use kernel\Foundation\File\FileRemoteStorage;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Platform\DiscuzX\Service\File\DiscuzXOSSService;

class DiscuzXFileRemoteStorageOSSGetUploadAuthController extends FileRemoteStorageOSSGetUploadAuthController
{
  public function data()
  {
    $Body = $this->body->some();

    $FilePathInfo = pathinfo($Body['sourceFileName']);

    $ObjectFileName = uniqid() . "." . $FilePathInfo['extension'];
    $FileKey = FileRemoteStorage::combinedFileKey($Body['filePath'], $ObjectFileName);

    $Auth = DiscuzXOSSService::getAccessAuth($FileKey, 600, [], [], "put");
    if ($Auth->error) return $Auth;
    $FileName = $FilePathInfo['basename'];

    DiscuzXFilesModel::singleton()->add($FileKey, $Body['sourceFileName'], $ObjectFileName, $Body['filePath'], $Body['size'], $FilePathInfo['extension'], null, DiscuzXFileStorage::PRIVATE, true);

    return [
      "fileKey" => $FileKey,
      "sourceFileName" => $Body['sourceFileName'],
      "filePath" => $Body['filePath'],
      "fileName" =>  $FileName,
      "size" => $Body['size'],
      "extension" => $FilePathInfo['extension'],
      "auth" => $Auth->getData()
    ];
  }
}
