<?php

namespace kernel\Foundation\Extension;


use kernel\Foundation\Config;
use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\Provisioner;

include_once \libfile("function/plugin");

class ExtensionProvisioner extends Provisioner
{
  private $extensionsPath = NULL;
  private $extensionId = NULL;
  private $namespace = NULL;
  /**
   * 构建函数
   *
   * @param string $pluginId 应用ID
   * @param string $extensionId 扩展ID
   * @param string $fromVersion 本地版本
   * @param string $extensionsPath 扩展路径
   */
  public function __construct($pluginId, $extensionId, $fromVersion, $extensionsPath = NULL)
  {
    parent::__construct($pluginId, $fromVersion);
    if ($extensionsPath) {
      $this->extensionsPath = $this->pluginPath . "/" . $extensionsPath;
      $this->namespace = "\\" . $pluginId . "\\" . \str_replace("/", "\\", $extensionsPath);
    } else {
      $this->extensionsPath = $this->pluginPath . "/Extensions/$extensionId";
      $this->namespace = "\\" . $pluginId . "\\Extensions\\" . $extensionId;
    }
    $this->extensionId = $extensionId;
  }
  /**
   * 安装扩展
   *
   */
  public function install()
  {
    $installFile = $this->extensionsPath . "/Provisioner/Install/Install.php";
    if (\file_exists($installFile)) {
      $className = $this->namespace . "\\Provisioner\\Install\\Install";
      $installInstance = new $className();
      $installInstance->handle();
    }
    return $this;
  }
  /**
   * 运行安装sql
   *
   */
  public function runInstallSql()
  {
    $multipleEncode = Config::get("multipleEncode", $this->pluginId);
    $sqlPath = $this->extensionsPath . "/Provisioner/Install";

    if ($multipleEncode) {
      $sqlPath .= "/" . $this->Charset . "/install.sql";
      if (!\file_exists($sqlPath)) {
        $sqlPath .= "/" . $this->Charset . ".sql";
      }
    }
    if (!\file_exists($sqlPath)) {
      $sqlPath = $this->extensionsPath . "/Provisioner/Install/install.sql";
    }
    if (!\file_exists($sqlPath)) {
      return $this;
    }
    $sql = \file_get_contents($sqlPath);
    \runquery($sql);

    return $this;
  }
  /**
   * 升级扩展
   *
   */
  public function upgrade()
  {
    $upgradeFilesRootPath = $this->extensionsPath . "/Provisioner/Upgrade/Files";
    $namespace = $this->namespace;
    $this->scanDirAndVersionCompare($upgradeFilesRootPath, function ($version, $fileName) use ($upgradeFilesRootPath, $namespace) {
      $filePath = $upgradeFilesRootPath . "/$fileName.php";
      if (\file_exists($filePath)) {
        $className = $namespace . "\Provisioner\Upgrade\Files\\$fileName";
        $upgradeItemInstance = new $className();
        $upgradeItemInstance->handle();
      }
    });
    return $this;
  }
  /**
   * 运行升级文件
   *
   */
  public function runUpgradeSql()
  {
    $sqlFileDirPath = $this->extensionsPath . "/Provisioner/Upgrade";
    $multipleEncode = Config::get("multipleEncode", $this->pluginId);
    if ($multipleEncode) {
      $sqlFileDirPath .= "/" . $this->Charset;
    } else {
      $sqlFileDirPath .= "/SQL";
    }
    if (!is_dir($sqlFileDirPath)) {
      return $this;
    }

    $this->scanDirAndVersionCompare($sqlFileDirPath, function ($version, $fileName) use ($sqlFileDirPath) {
      $sqlFilePath = $sqlFileDirPath .= "/$fileName.sql";
      if (!\file_exists($sqlFilePath)) {
        return $this;
      }
      $sqlContent = \file_get_contents($sqlFilePath);
      \runquery($sqlContent);
    });
    return $this;
  }
  /**
   * 清除扩展 Provisioner 目录下的所有文件和文件夹
   *
   * @return boolean
   */
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return FileSystem::deleteDirectory($this->extensionsPath . "/Provisioner");
  }
  /**
   * 清除扩展 Provisioner 下的 Install 文件和文件夹
   *
   * @return boolean
   */
  public function cleanInstall()
  {
    return FileSystem::deleteDirectory($this->extensionsPath . "/Provisioner/Install");
  }
  /**
   * 清除扩展 Provisioner 下的 Upgrade 文件夹
   *
   * @return boolean
   */
  public function cleanUpgrade()
  {
    return FileSystem::deleteDirectory($this->extensionsPath . "/Provisioner/Upgrade");
  }
}
