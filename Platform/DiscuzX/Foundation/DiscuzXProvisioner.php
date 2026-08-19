<?php

namespace kernel\Platform\DiscuzX\Foundation;
use kernel\Foundation\FileSystem\FileSystem;

use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\Provisioner;
use kernel\Platform\DiscuzX\Foundation\DiscuzXApp;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

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
    FileSystem::deleteDirectory(F_DISCUZX_DATA_PLUGIN);
  }
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return FileSystem::deleteDirectory(FileHelper::combinedFilePath(FileSystem::root(), "Provisioner"));
  }
  public function cleanInstall()
  {
    return FileSystem::deleteDirectory(FileHelper::combinedFilePath(FileSystem::root(), "Provisioner", "Install"));
  }
  public function cleanUpgrade()
  {
    return FileSystem::deleteDirectory(FileHelper::combinedFilePath(FileSystem::root(), "Provisioner", "Upgrade"));
  }
}
