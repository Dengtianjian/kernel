<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\File\Files;
use kernel\Service\File\FileRemoteStorageService;
use kernel\Service\File\FileStorageService;

/**
 * 获取上传到远程存储授权信息  
 * 应用到无存储状态的文件，也就是不用登记入库的文件上传到远程存储库  
 * 如果是使用OSS等其它远程存储，请根据实际业务需求覆写当前控制器，并且覆盖路由
 */
class GetUploadRemoteAuthController extends AuthController
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
    $FileKey = Files::combinedFileKey($Body['filePath'], $fileName);
    $Auth = FileRemoteStorageService::getFileAuth($FileKey, 600, [], [], "post");
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
