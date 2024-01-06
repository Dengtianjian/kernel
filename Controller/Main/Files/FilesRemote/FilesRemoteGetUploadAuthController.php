<?php

namespace kernel\Controller\Main\Files\FilesRemote;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\File\Files;
use kernel\Foundation\Validate\ValidateRules;
use kernel\Service\File\FileRemoteStorageService;
use kernel\Service\File\FilesRemoteService;

/**
 * 获取上传到远程存储授权信息  
 * 应用到无存储状态的文件，也就是不用登记入库的文件上传到远程存储库  
 * 如果是使用OSS等其它远程存储，请根据实际业务需求覆写当前控制器，并且覆盖路由
 */
class FilesRemoteGetUploadAuthController extends AuthController
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

    $Auth = FilesRemoteService::getFileAuth($FileKey, 1800, [], [], "post");
    if ($Auth->error) return $Auth;

    return [
      "fileKey" => $FileKey,
      "sourceFileName" => $Body['sourceFileName'],
      "filePath" => $Body['filePath'],
      "fileName" => pathinfo($FileKey, PATHINFO_BASENAME),
      "size" => $Body['size'],
      "extension" => $FilePathInfo['extension'],
      "auth" => $Auth->getData()
    ];
  }
}
