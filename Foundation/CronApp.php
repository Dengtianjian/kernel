<?php

namespace kernel\Foundation;

use kernel\Foundation\File\FileHelper;

/**
 * KERNEL标识符
 */
if (!defined("F_KERNEL")) {
  define("F_KERNEL", true);
}

class CronApp extends App
{
  function __construct($AppId, $KernelId = "kernel")
  {
    parent::__construct($AppId, $KernelId);

    $this->loadTasks();
  }
  protected function loadTasks()
  {
    $CronInstallFile = FileHelper::combinedFilePath(F_APP_ROOT, "crons.php");
    if (file_exists($CronInstallFile)) {
      include_once($CronInstallFile);
    }

    $CronsDirectoryPath = FileHelper::combinedFilePath(F_APP_ROOT, "Crons");
    if (is_dir($CronsDirectoryPath)) {
      $CronInstallFiles = FileHelper::recursionScanDir($CronsDirectoryPath);
      foreach ($CronInstallFiles as $FileItem) {
        include_once($FileItem);
      }
    }
  }
  protected function executeController($Controller)
  {
    $Ins = new $Controller($this->request());
    $Ins->data();
  }
  function run()
  {
    $C = new Cron();
    $Controllers = $C->list();

    foreach ($Controllers as $controller) {
      if (is_callable($controller)) {
        $controller();
      } else {
        $this->executeController($controller);
      }
    }
  }
}
