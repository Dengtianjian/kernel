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
  public function install()
  {
    set_time_limit(0); //* 不超时断开Http链接
    $IUUDirPath = F_APP_ROOT . "/Iuu";
    $versionFilePath = " $IUUDirPath/.version";
    if (file_exists($versionFilePath)) {
      Response::error(400, "AlreadyInitialized:400001", "已经初始化过了");
    }
    $key = RequestService::request()->body("key");
    $keyFilePath =  " $IUUDirPath/.key";
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
    $initTagFile = "$IUUDirPath/.init";
    if (file_exists($initTagFile)) {
      Response::error(400, "Initing:400000", "已经在初始化中了");
    }
    file_put_contents($initTagFile, time());
    Response::intercept(function () use ($initTagFile) {
      unlink($initTagFile);
    });

    Log::record("系统安装：" . Config::get("version"));
    file_put_contents($versionFilePath, Config::get("version"));

    return true;
  }
  public function upgrade()
  {
    set_time_limit(0); //* 不超时断开Http链接
    $key = RequestService::request()->body("key");
    if (Iuu::verificationKey($key) === false) {
      Response::error(400, "WrongKey:400000", "密钥错误");
    }

    $versionFilePath = $this->IuuPath . "/.version";
    file_put_contents($versionFilePath, Config::get("version"));
    Log::record("系统升级到：" . Config::get("version"));
    return true;
  }
  private function installSys()
  {
    $installFile = $this->IuuPath . "/Install/install.php";
    if (\file_exists($installFile)) {
      include_once($installFile);
      $className = "\\" . \F_APP_ID . "\Iuu\Install\Install";
      $installInstance = new $className();
      $installInstance->handle();
    }

    $sqlPath = $this->IuuPath . "/Install/install.sql";
    if (\file_exists($sqlPath)) {
      $sql = \file_get_contents($sqlPath);
      DB::query($sql);
    }
    Log::record("系统安装：" . Config::get("version"));
    return $this;
  }
  protected function scanDirAndVersionCompare($upgradeRealtedFileDir, $callBack = null)
  {
    if (!\is_dir($upgradeRealtedFileDir)) {
      return true;
    }
    $upgradeFilesDir = @\scandir($upgradeRealtedFileDir);
    foreach ($upgradeFilesDir as $dirItem) {
      if ($dirItem === "." || $dirItem === "..") {
        continue;
      }
      $version = substr($dirItem, 8);
      $version = str_replace("_", ".", $version);
      if (version_compare($this->version, $version, "<=") === true) {
        $callBack($version, $dirItem);
      }
    }
  }
  private function upgradeSys()
  {
    $upgradeDirPath = $this->IuuPath . "/Upgrade";
    $this->scanDirAndVersionCompare($this->IuuPath . "/Upgrade", function ($version, $fileName) use ($upgradeDirPath) {
      $versionRootPath = $upgradeDirPath . "/$fileName";
      $filePath = $versionRootPath . "/Upgrade.php";

      if (\file_exists($filePath)) {
        $className = F_APP_ID . "\\Iuu\Upgrade\\$fileName\\Upgrade";
        $upgradeItemInstance = new $className();
        $upgradeItemInstance->handle();
      }

      $sqlFilePath = $versionRootPath . "/Upgrade.sql";
      if (!\file_exists($sqlFilePath)) {
        return $this;
      }
      $sqlContent = \file_get_contents($sqlFilePath);
      DB::query($sqlContent);
    });
    $versionFilePath = $this->IuuPath . "/.version";
    file_put_contents($versionFilePath, Config::get("version"));
    Log::record("系统升级到：" . Config::get("version"));
    return $this;
  }
  private function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return File::deleteDirectory($this->IuuPath . "/Iuu");
  }
  private function cleanInstall()
  {
    return File::deleteDirectory($this->IuuPath . "/Install");
  }
  private function cleanUpgrade()
  {
    return File::deleteDirectory($this->IuuPath . "/Upgrade");
  }
  public static function verificationKey(string $key)
  {
    $keyFilePath = F_APP_ROOT . "/Iuu/.key";
    if (!file_exists($keyFilePath)) {
      Log::record("校验Iuu的Key：.key文件不存在");
      return false;
    }
    $keyContent = file_get_contents($keyFilePath);
    if ($key !== $keyContent) {
      Log::record("校验Iuu的Key：输入的key错误");
      return false;
    }
  }
}
