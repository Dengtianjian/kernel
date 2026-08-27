<?php

use kernel\Foundation\Router\Route;
use kernel\Foundation\Config;

//* 测试专用
if (Config::get("mode") === "development") {
  Route::any("/", kernel\Controller\Main\IndexController::class);
}
