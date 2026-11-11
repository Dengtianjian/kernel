<?php

use kernel\Foundation\App;
use kernel\Foundation\HTTP\Request;
use kernel\Support\Output;

// include_once("./vendor/autoload.php");

// if (file_exists("./vendor/autoload.php")) {
//   include_once("./vendor/autoload.php");
// }

//* 构造即装配：第一个参数是装配类（闭包亦可），其后是应用 ID，构造返回后应用即可 run()
// $App = new App(\kernel\Setup\Bootstrap::class, "kernel");
// $App = new DiscuzXApp(\kernel\Setup\Bootstrap::class, "kernel");
// $App->run();