<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Validate\ValidateRules;

/**
 * 获取文件授权信息  
 * 该控制器并不会自动注册，请根据实际业务需求覆写当前控制器，并且覆盖路由
 */
class GetFileAuthController extends FileBaseController
{
  public $body = [
    "sourceFileName" => "string",
    "filePath" => "string",
    "fileSize" => "int",
    "width" => "int",
    "height" => "int"
  ];
  public function data()
  {
    // return $this->platform->getFileAuth($FileKey, 1800);
    return [
      "fileKey" => NULL,
      "remoteFileKey" => NULL,
      "auth" => NULL,
      "previewURL" => NULL,
      "accessControl" => NULL
    ];
  }
}
