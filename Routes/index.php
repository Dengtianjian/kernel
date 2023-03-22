<?php

use kernel\Foundation\Router;
use kernel\App\Main\TestController;
use kernel\Foundation\Config;

//* 测试专用
if (Config::get("mode") === "development") {
  Router::any("/", TestController::class);
}