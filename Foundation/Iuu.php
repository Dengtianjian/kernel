<?php

namespace kernel\Foundation;

use kernel\Foundation\Database\PDO\DB;

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
  public function upgrade()
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
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return File::deleteDirectory($this->IuuPath . "/Iuu");
  }
  public function cleanInstall()
  {
    return File::deleteDirectory($this->IuuPath . "/Install");
  }
  public function cleanUpgrade()
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
