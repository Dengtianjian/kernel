<?php

use kernel\Foundation\App;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalDiscuzXMiddleware;
use kernel\Middleware\GlobalTestMiddleware;
use kernel\Middleware\GlobalWechatOfficialAccountMiddleware;
use kernel\Platform\DiscuzX\Foundation\DiscuzXApp;

error_reporting(E_ALL);

include_once DISCUZ_ROOT . "/source/plugin/gstudio_kernel/Platform/DiscuzX/Foundation/DiscuzXAutoload.php";

$App = new DiscuzXApp("gstudio_kernel");
$App->run();
