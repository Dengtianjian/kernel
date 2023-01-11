<?php

use kernel\App;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalAuthMiddleware;
use kernel\Platform\DiscuzX\Foundation\DiscuzXApp;

if (!defined("F_KERNEL")) {
  exit("Access Denied");
}

include_once(DISCUZ_ROOT . "source/plugin/kernel/Autoload.php");

$app = new DiscuzXApp("kernel");
$app->setMiddlware(GlobalAuthMiddleware::class);
$app->run();
