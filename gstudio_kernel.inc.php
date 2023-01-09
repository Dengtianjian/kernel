<?php

use kernel\App;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalAuthMiddleware;

if (!defined("F_KERNEL")) {
  exit("Access Denied");
}

include_once(DISCUZ_ROOT . "source/plugin/kernel/Autoload.php");

$app = new App("kernel");
$app->setMiddlware(GlobalAuthMiddleware::class);
$app->init();
