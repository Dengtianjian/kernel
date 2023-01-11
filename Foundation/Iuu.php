<?php

namespace gstudio_kernel\Foundation;

use GuzzleHttp\Promise\Is;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

/** Install Upgrade Uninstall */
class Iuu
{
  protected $pluginId = null;
  protected $fromVersion = null;
  protected $latestVersion = null;
  protected $pluginPath = null;
  protected $Charset = null;
  public function __construct($pluginId, $fromVersion)
  {
    $this->pluginId = $pluginId;
    $this->pluginPath = "source/plugin/$pluginId";
    $this->fromVersion = $fromVersion;
    $this->latestVersion = \getglobal("setting/plugins/version/$pluginId");
    $this->Charset = \strtoupper(\CHARSET);

    if (!defined("F_APP_ID")) {
      define("F_APP_ID", $this->pluginId);
    }
    if (!defined("F_APP_BASE")) {
      define("F_APP_BASE", $this->pluginPath);
    }
    if (!defined("F_APP_DATA")) {
      define("F_APP_DATA", File::genPath(DISCUZ_ROOT, "data", "plugindata", $this->pluginId));
    }
  }
  public function install()
  {
    $installFile =  $this->pluginPath . "/Iuu/Install/install.php";
    if (\file_exists($installFile)) {
      // include_once($installFile);
      $className = "\\" . $this->pluginId . "\Iuu\Install\Install";
      new $className();
    }
    if (!is_dir(F_APP_DATA)) {
      mkdir(F_APP_DATA, 0777, true);
    }
    return $this;
  }
  public function runInstallSql()
  {
    $multipleEncode = Config::get("multipleEncode");
    $sqlPath =  $this->pluginPath . "/Iuu/Install";
    if ($multipleEncode) {
      $sqlPath .= "/" . $this->Charset . ".sql";
    }
    if (!\file_exists($sqlPath) || is_dir($sqlPath)) {
      $sqlPath =  $this->pluginPath . "/Iuu/Install/install.sql";
    }

    if (!\file_exists($sqlPath)) {
      return $this;
    }
    $sql = \file_get_contents($sqlPath);
    \runquery($sql);

    return $this;
  }
  public function upgrade()
  {
    $UpgradeListFile = File::genPath($this->pluginPath, "Iuu", "UpgradeList.php");
    if (!file_exists($UpgradeListFile)) return true;
    $UpgradeList = include_once($UpgradeListFile);

    foreach ($UpgradeList as $Version => $VersionCallback) {
      if (version_compare($this->fromVersion, $Version, "<") === true) {
        if (is_callable($VersionCallback)) {
          $VersionCallback();
        } else {
          new $VersionCallback();
        }
      }
    }
    return $this;
  }
  public function uninstall()
  {
    File::deleteDirectory(File::genPath(\getglobal("setting/attachurl"), "plugin/" . F_APP_ID));
    File::deleteDirectory(F_APP_DATA);
  }
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return File::deleteDirectory($this->pluginPath . "/Iuu");
  }
  public function cleanInstall()
  {
    return File::deleteDirectory($this->pluginPath . "/Iuu/Install");
  }
  public function cleanUpgrade()
  {
    return File::deleteDirectory($this->pluginPath . "/Iuu/Upgrade");
  }
}
