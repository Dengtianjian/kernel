<?php

use kernel\Facades\Config;
use kernel\Foundation\Router\Route;

//* 测试专用
if (Config::get("mode") === "development") {
  Route::any("/", kernel\Controllers\Main\IndexController::class);
}
