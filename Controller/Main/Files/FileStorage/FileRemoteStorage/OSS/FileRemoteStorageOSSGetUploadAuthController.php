<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\File\Files;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\Validate\ValidateRules;
use kernel\Model\FilesModel;
use kernel\Service\File\FileOSSStorageService;

class FileRemoteStorageOSSGetUploadAuthController extends AuthController
{
  public $body = [
    "filePath" => "string",
    "sourceFileName" => "string",
    "scope" => "array",
    "size" => "int"
  ];
  public function __construct($R)
  {
    $this->bodyValidator = [
      "sourceFileName" => (new ValidateRules())->type("string", "请传入正确的原文件名称")->required("请传入原文件名称")->minLength(1, "请传入正确的原文件名称")->custom(function ($value) {
        $PathInfo = pathinfo($value);
        if (!$PathInfo['basename']) {
          return $this->response->error(400, 400, "请传入正确的原文件名称");
        }
        if (!$PathInfo['extension']) {
          return $this->response->error(400, 400, "请传入正确的原文件名称");
        }

        return $this->response->success(true);
      })
    ];

    parent::__construct($R);
  }
  public function data()
  {
    $Body = $this->body->some();

    $FilePathInfo = pathinfo($Body['sourceFileName']);

    $ObjectFileName = uniqid() . "." . $FilePathInfo['extension'];
    $FileKey = Files::combinedFileKey($Body['filePath'], $ObjectFileName);

    $Auth = FileOSSStorageService::getAccessAuth($FileKey, 600, [], [], "put");
    if ($Auth->error) return $Auth;
    $FileName = $FilePathInfo['basename'];

    FilesModel::singleton()->add($FileKey, $Body['sourceFileName'], $ObjectFileName, $Body['filePath'], $Body['size'], $FilePathInfo['extension'], null, FileStorage::PRIVATE, true);

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
