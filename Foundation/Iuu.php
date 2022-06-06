<?php

namespace kernel\Foundation;

use kernel\Foundation\Database\PDO\DB;
use kernel\Service\RequestService;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/** Install Upgrade Uninstall */
class Iuu
{
  protected string $IuuPath = "";
  protected string $version = "";
  public function __construct()
  {
    $this->IuuPath = F_APP_ROOT . "/Iuu";
    if (file_exists($this->IuuPath . "/.version")) {
      $this->version = file_get_contents($this->IuuPath . "/.version");
    }
  }
  public function runSqlFile(string $filePath): bool
  {
    if (!file_exists($filePath)) return false;
    return DB::query(file_get_contents($filePath));
  }
  public static function verifyKey(string $key, bool $directResponse = false): bool
  {
    $keyFilePath = File::genPath(F_APP_ID, "Iuu", ".key");
    if (!file_exists($keyFilePath)) {
      Log::record("校验Iuu的Key：.key文件不存在");
      if ($directResponse) {
        Response::error(500, "KeyFileNotExist:500001", "系统错误", [], [
          "content" => "IUU下的.key文件不存在",
          "keyPath" => $keyFilePath
        ]);
      }
      return false;
    }
    $keyContent = file_get_contents($keyFilePath);
    if ($key !== $keyContent) {
      Log::record("校验Iuu的Key：输入的key错误");
      if ($directResponse) {
        Response::error(400, "AlreadyInitialized:400001", "安装密钥错误");
      }
      return false;
    }
    return true;
  }
  public function install(string $key)
  {
    Iuu::verifyKey($key, true);
    set_time_limit(0); //* 不超时断开Http链接
    $versionFilePath = File::genPath($this->IuuPath, ".version");
    if (file_exists($versionFilePath)) {
      Response::error(400, "AlreadyInitialized:400002", "已经初始化过了");
    }
    $initedMarkFile = File::genPath($this->IuuPath, ".init");
    if (file_exists($initedMarkFile)) {
      Response::error(400, "Initing:400000", "已经在初始化中了");
    }
    file_put_contents($initedMarkFile, time());
    Response::intercept(function () use ($initedMarkFile) {
      unlink($initedMarkFile);
    });
    Log::record("系统安装：" . Config::get("version"));
    file_put_contents($versionFilePath, Config::get("version"));

    return true;
  }
  public function isInstalled(): bool
  {
    $versionFile = File::genPath($this->IuuPath, ".version");
    return file_exists($versionFile);
  }
  public function compareVersion(array $versions, callable $callback): void
  {
    foreach ($versions as $versionKey => $version) {
      if (is_callable($version)) {
        $version($this->version, $version, version_compare($this->version, $versionKey, ">="));
        $callback($this->version, $version, version_compare($this->version, $versionKey, ">="));
      } else {
        call_user_func($callback, $this->version, $version, version_compare($this->version, $version, ">="));
      }
    }
  }
  public function upgrade(string $key): bool
  {
    set_time_limit(0); //* 不超时断开Http链接
    Iuu::verifyKey($key, true);
    $versionFilePath = File::genPath($this->IuuPath, ".version");
    file_put_contents($versionFilePath, Config::get("version"));
    Log::record("系统升级到：" . Config::get("version"));

    return true;
  }
}