<?php

namespace kernel\Foundation;
use kernel\Foundation\FileSystem\FileSystem;

use kernel\Foundation\FileSystem\FileHelper;


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
    $installFile = FileSystem::root() . "/Iuu/Install/Install.php";
    if (\file_exists($installFile)) {
      // include_once($installFile);
      $className = "\\" . App::id() . "\Iuu\Install\Install";
      new $className();
    }
    if (!is_dir(FileSystem::data())) {
      mkdir(FileSystem::data(), 0777, true);
    }
    return $this;
  }
  public function upgrade($TargetVersion = null, $UpgradeCallback = null, $UpgradeListFileName = null): bool|Iuu
  {
    $UpgradeListFile = $UpgradeListFileName ? $UpgradeListFileName : FileHelper::combinedFilePath(FileSystem::root(), "Iuu", "UpgradeList.php");
    if (!file_exists($UpgradeListFile))
      return true;
    $UpgradeList = include_once($UpgradeListFile);
    ksort($UpgradeList);
    $currentVersion = $this->fromVersion;
    foreach ($UpgradeList as $Version => $VersionCallback) {
      if ($TargetVersion && version_compare($Version, $TargetVersion, ">") === true)
        continue;
      if (version_compare($currentVersion, $Version, ">=") === true)
        continue;

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
    $uninstallFile = FileSystem::root() . "/Iuu/Uninstall/Uninstall.php";
    if (\file_exists($uninstallFile)) {
      // include_once($installFile);
      $className = "\\" . App::id() . "\Iuu\Uninstall\Uninstall";
      new $className();
    }
    // FileSystem::deleteDirectory(FileSystem::data());
  }
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return FileSystem::deleteDirectory(FileSystem::root() . "/Iuu");
  }
  public function cleanInstall()
  {
    return FileSystem::deleteDirectory(FileSystem::root() . "/Iuu/Install");
  }
  public function cleanUpgrade()
  {
    return FileSystem::deleteDirectory(FileSystem::root() . "/Iuu/Upgrade");
  }
}
