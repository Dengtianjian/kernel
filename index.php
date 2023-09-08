<?php

use kernel\Foundation\App;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalDiscuzXMiddleware;
use kernel\Middleware\GlobalTestMiddleware;
use kernel\Middleware\GlobalWechatOfficialAccountMiddleware;
use kernel\Platform\DiscuzX\Foundation\DiscuzXApp;

include_once("../kernel/vendor/autoload.php");

if (file_exists("./vendor/autoload.php")) {
  include_once("./vendor/autoload.php");
}

// $App = new App("kernel");
// $App = new DiscuzXApp("kernel");
// $App->setMiddlware(GlobalWechatOfficialAccountMiddleware::class);
// $App->run();
