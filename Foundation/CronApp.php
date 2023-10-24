<?php

namespace kernel\Foundation;

use Exception as GlobalException;
use gstudio_kernel\Foundation\ReturnResult\ReturnList;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\Response\ResponsePagination;

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
    $CronInstallFile = File::genPath(F_APP_ROOT, "crons.php");
    if (file_exists($CronInstallFile)) {
      include_once($CronInstallFile);
    }

    $CronsDirectoryPath = File::genPath(F_APP_ROOT, "Crons");
    if (is_dir($CronsDirectoryPath)) {
      $CronInstallFiles = File::recursionScanDir($CronsDirectoryPath);
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
