<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Storage\FileStorage;
use kernel\Modules\Auth\Auth;

class GetFileAuthController extends AuthController
{
  protected $requestBodySerializes = [
    "disk" => "string",

    "expires" => "int",
    "fileKey" => "string",

    //* 上传参数
    "name" => "string",
    "path" => "string",
    "size" => "int",
    "width" => "int",
    "height" => "int",
    "accessControl" => "string",
    "ref" => "string",
    "type" => "string"
  ];

  /**
   * 文件名
   * @var string
   */
  protected $fileKey = null;

  public function data(string $method)
  {
    if (!in_array($method, ["post", "get", "patch", "delete"])) return $this->fail(400, 400, "非法的方法参数");

    $this->fileKey = $fileKey = $this->body("fileKey");

    if ($method !== "post" && !$fileKey) return $this->fail(400, 400, "文件名不可为空");

    $auth = $this->$method($fileKey);
    if ($auth instanceof Response) return $auth;

    return [
      "key" => $this->fileKey,
      "method" => $method,
      "auth" => $auth
    ];
  }
  protected function patch(string $fileKey)
  {
    if (!Auth::logged()) return $this->fail(403, 403, "抱歉，您无权获取修改文件授权");

    return Storage::createAuthParams($fileKey, $this->body("expires") ?: 1800, [], [], "patch");
  }
  protected function get(string $fileKey)
  {
    return Storage::createAuthParams($fileKey, $this->body("expires") ?: 1800, [], [], "get");
  }
  protected function post(?string $fileKey)
  {
    if (!Auth::logged()) return $this->fail(403, 403, "抱歉，您无权获取上传文件授权");

    $body = $this->requestBody->some(
      [
        "name",
        "path",
        "size",
        "width",
        "height",
        "accessControl",
        "ref",
        "type"
      ]
    );
    $pathInfo = pathinfo($body['name']);
    $objectFileName = Storage::generateFileKey($pathInfo['extension']);

    if ($fileKey) {
      $this->fileKey = $fileKey;
      $pathInfo = pathinfo($fileKey);
      $objectFileName = $pathInfo['basename'];
    } else {
      $this->fileKey = $fileKey = Storage::buildFileKey($body['path'], $objectFileName);
    }

    $accessControl = ($this->body("accessControl") !== null) && $this->body("accessControl") ? $this->body("accessControl") : FileStorage::PUBLIC_READ;

    $disk = Storage::disk($this->body("disk") ?: null);

    if (Storage::dataSave()) {
      Storage::add($fileKey, $body['name'], $objectFileName, $body['path'], $body['size'], $pathInfo['extension'], null, Auth::userId(), $accessControl, $disk->name(), $body['ref'] ?: null, $body['type'] ?: null, $body['width'], $body['height']);
    }

    $expires = $this->body("expires") ?: 600; //* 默认是 10 分钟有效期，如果文件大小超过 10M，就会通过尺寸大小来重新计算有效期，会基于 10 分钟的基础上去增加时间
    if ($body['size'] && $body['size'] > 1024 * 1024 * 10) {
      $size = $body['size'] - (1024 * 1024 * 10);
      $expires += ceil(($size / 1024 / 1024) * 60);
    }

    $savePath = Path::join(Path::storage(), $body['path']);
    mkdir($savePath, 0755, true);
    chmod($savePath, 755);

    $auth = Storage::createAuthParams($fileKey, $expires, [], [], "post");

    return $this->success([
      "key" => $this->fileKey,
      "method" => "post",
      "accessControl" => $accessControl,
      "auth" => $auth
    ]);
  }
  protected function delete(string $fileKey)
  {
    if (!Auth::logged()) return $this->fail(403, 403, "抱歉，您无权获取删除文件授权");

    return Storage::createAuthParams($fileKey, $this->body("expires") ?: 1800, [], [], "delete");
  }
}
