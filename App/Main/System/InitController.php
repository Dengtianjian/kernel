<?php

namespace kernel\App\Main\System;

use kernel\Foundation\Config;
use kernel\Foundation\Controller;
use kernel\Foundation\Iuu;
use kernel\Foundation\Request;
use kernel\Foundation\Response;

class InitController extends Controller
{
  public function data(Request $R)
  {
    set_time_limit(0); //* 不超时断开Http链接
    $versionFilePath = F_APP_ROOT . "/Iuu/.version";
    if (file_exists($versionFilePath)) {
      Response::error(400, "AlreadyInitialized:400001", "已经初始化过了");
    }
    $key = $R->body("key");
    $keyFilePath = F_APP_ROOT . "/Iuu/.key";
    if (!file_exists($keyFilePath)) {
      Response::error(500, "KeyFileNotExist:500001", "系统错误", [], [
        "content" => "IUU下的.key文件不存在",
        "keyPath" => $keyFilePath
      ]);
    }
    $keyContent = file_get_contents($keyFilePath);
    if ($key !== $keyContent) {
      Response::error(400, "WrongKey:400001", "密钥错误");
    }
    $initTagFile = F_APP_ROOT . "/Iuu/.init";
    if (file_exists($initTagFile)) {
      Response::error(400, "Initing:400000", "已经在初始化中了");
    }
    file_put_contents($initTagFile, time());
    Response::intercept(function () use ($initTagFile) {
      unlink($initTagFile);
    });
    $IUU = new Iuu();
    $IUU->install();
    file_put_contents($versionFilePath, Config::get("version"));
    return true;
  }
}
