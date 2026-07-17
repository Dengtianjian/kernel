<?php

use kernel\Foundation\Router;
use kernel\Foundation\Config;

//* 测试专用
if (Config::get("mode") === "development") {
  Router::any("/", kernel\Controller\Main\IndexController::class);
}
