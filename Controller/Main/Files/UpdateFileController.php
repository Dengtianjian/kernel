<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\FileSystem\Storage\FileStorage;
use kernel\Foundation\Validation\Rule;

class UpdateFileController extends Controller
{
  public $requestBodySerializes = [
    "disk" => "string",
    "ref" => "string",
    "type" => "string",
    "owner_id" => "string",
    "access_control" => "string",
  ];

  public function __construct($R)
  {
    $this->requestBodyValidator = [
      "disk" => Rule::nullable()->type("string", "磁盘名称格式错误")->maxLength(32, "磁盘名称过长"),
      "ref" => Rule::nullable()->type(["string", "double", "int", "float"], "引用ID格式错误")->maxLength(48, "引用ID过长"),
      "type" => Rule::nullable()->type(["string", "double", "int", "float"], "业务类型格式错误")->maxLength(128, "业务类型过长"),
      "owner_id" => Rule::nullable()->type("string", "所属ID格式错误")->maxLength(32, "所属ID过长"),
      "access_control" => Rule::nullable()->type("string", "访问控制权限格式错误")->in([
        FileStorage::PRIVATE,
        FileStorage::PUBLIC_READ,
        FileStorage::PUBLIC_READ_WRITE,
        FileStorage::AUTHENTICATED_READ,
        FileStorage::AUTHENTICATED_READ_WRITE,
      ], "访问控制权限不合法"),
    ];

    parent::__construct($R);
  }

  public function data($fileKey)
  {
    if (!Storage::authorizeOperation($fileKey, "write")) return Storage::return();
    if (!Storage::dataSave()) return $this->fail(400, 400, "修改文件信息功能已关闭");

    if (!Storage::exists($fileKey)) return $this->fail(404, 404, "文件不存在");

    return Storage::model()->where("key", $fileKey)->update($this->body());
  }
}
