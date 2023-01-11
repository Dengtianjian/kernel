<?php

use gstudio_kernel\App;
use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Middleware\GlobalAuthMiddleware;

if (!defined("IN_DISCUZ")) {
  exit("Access Denied");
}

include_once(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php");

$app = new App("gstudio_kernel");
$app->setMiddlware(GlobalAuthMiddleware::class);
$app->init();
