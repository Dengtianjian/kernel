<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File;
use kernel\Foundation\Iuu;
use kernel\Platform\DiscuzX\Foundation\DiscuzXApp;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

define("F_KERNEL", 1);

/** Install Upgrade Uninstall */
class DiscuzXIuu extends Iuu
{
  protected $fromVersion = null;
  protected $latestVersion = null;
  protected $pluginPath = null;
  protected $Charset = null;
  public function __construct($pluginId, $fromVersion = null)
  {
    $this->fromVersion = $fromVersion;
    $this->latestVersion = \getglobal("setting/plugins/version/$pluginId");
    $this->Charset = \strtoupper(\CHARSET);

    new DiscuzXApp($pluginId);
  }
  public function install()
  {
    parent::install();
    if (!is_dir(F_DISCUZX_DATA_PLUGIN)) {
      mkdir(F_DISCUZX_DATA_PLUGIN, 0777, true);
    }
    return $this;
  }
  public function uninstall()
  {
    parent::uninstall();
    File::deleteDirectory(F_DISCUZX_DATA_PLUGIN);
    // File::deleteDirectory(F_APP_DATA);
  }
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return File::deleteDirectory(File::genPath(F_APP_ROOT, "Iuu"));
  }
  public function cleanInstall()
  {
    return File::deleteDirectory(File::genPath(F_APP_ROOT, "Iuu", "Install"));
  }
  public function cleanUpgrade()
  {
    return File::deleteDirectory(File::genPath(F_APP_ROOT, "Iuu", "Upgrade"));
  }
}
