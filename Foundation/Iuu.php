<?php

namespace kernel\Foundation;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/** Install Upgrade Uninstall */
class Iuu
{
  protected $fromVersion = null;
  protected $latestVersion = null;
  public function __construct($appId, $fromVersion = null)
  {
    $this->fromVersion = $fromVersion;
    new App($appId);
  }
  public function install()
  {
    $installFile =  F_APP_ROOT . "/Iuu/Install/Install.php";
    if (\file_exists($installFile)) {
      // include_once($installFile);
      $className = "\\" . F_APP_ID . "\Iuu\Install\Install";
      new $className();
    }
    if (!is_dir(F_APP_DATA)) {
      mkdir(F_APP_DATA, 0777, true);
    }
    return $this;
  }
  public function upgrade($TargetVersion = null, $UpgradeCallback = null)
  {
    $UpgradeListFile = File::genPath(F_APP_ROOT, "Iuu", "UpgradeList.php");
    if (!file_exists($UpgradeListFile)) return true;
    $UpgradeList = include_once($UpgradeListFile);
    ksort($UpgradeList);
    $currentVersion = $this->fromVersion;
    foreach ($UpgradeList as $Version => $VersionCallback) {
      if ($TargetVersion && version_compare($Version, $TargetVersion, ">") === true) continue;
      if (version_compare($currentVersion, $Version, ">=") === true) continue;

      if (!is_null($VersionCallback)) {
        if (is_callable($VersionCallback)) {
          $VersionCallback();
        } else {
          new $VersionCallback();
        }
      }

      $currentVersion = $Version;
      if ($UpgradeCallback) {
        $UpgradeCallback($currentVersion);
      }
    }
    if (!array_key_exists($TargetVersion, $UpgradeList)) {
      if ($UpgradeCallback) {
        $UpgradeCallback($TargetVersion);
      }
    }

    return $this;
  }
  public function uninstall()
  {
    $uninstallFile =  F_APP_ROOT . "/Iuu/Uninstall/Uninstall.php";
    if (\file_exists($uninstallFile)) {
      // include_once($installFile);
      $className = "\\" . F_APP_ID . "\Iuu\Uninstall\Uninstall";
      new $className();
    }
    // File::deleteDirectory(F_APP_DATA);
  }
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return File::deleteDirectory(F_APP_ROOT . "/Iuu");
  }
  public function cleanInstall()
  {
    return File::deleteDirectory(F_APP_ROOT . "/Iuu/Install");
  }
  public function cleanUpgrade()
  {
    return File::deleteDirectory(F_APP_ROOT . "/Iuu/Upgrade");
  }
}
