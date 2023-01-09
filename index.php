<?php

use kernel\Foundation\App;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalDiscuzXMiddleware;
use kernel\Middleware\GlobalTestMiddleware;

include_once("../kernel/vendor/autoload.php");

if (file_exists("./vendor/autoload.php")) {
  include_once("./vendor/autoload.php");
}

$App = new App("kernel");
// $App->setMiddlware(GlobalDiscuzXMiddleware::class);
// $App->setMiddlware(function (\Closure $next, Request $R) {
//   $res = $next();
//   // $res->addData([
//   //   "username" => "admin",
//   //   "age" => 24
//   // ]);
//   return $res;
// });
// $App->setMiddlware(function ($next, Request $R, $a, $b) {
//   Output::printContent(2);
//   $next();
//   Output::printContent(5);
// }, [11, 12]);
// $App->setMiddlware(GlobalTestMiddleware::class, [
//   1, 2, 3, 4
// ]);
$App->run();
