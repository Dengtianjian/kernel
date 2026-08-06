<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\Filesystem;
use kernel\Foundation\Provisioner;
use kernel\Platform\DiscuzX\Foundation\DiscuzXApp;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

define("F_KERNEL", 1);

/** DiscuzX Provisioner: Install / Upgrade / Uninstall */
class DiscuzXProvisioner extends Provisioner
{
  protected $pluginPath = null;
  protected $Charset = null;
  public function __construct($pluginId, $fromVersion = null)
  {
    parent::__construct($pluginId, $fromVersion);

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
    Filesystem::deleteDirectory(F_DISCUZX_DATA_PLUGIN);
  }
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return Filesystem::deleteDirectory(FileHelper::combinedFilePath(F_APP_ROOT, "Provisioner"));
  }
  public function cleanInstall()
  {
    return Filesystem::deleteDirectory(FileHelper::combinedFilePath(F_APP_ROOT, "Provisioner", "Install"));
  }
  public function cleanUpgrade()
  {
    return Filesystem::deleteDirectory(FileHelper::combinedFilePath(F_APP_ROOT, "Provisioner", "Upgrade"));
  }
}
