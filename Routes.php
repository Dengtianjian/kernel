<?php

namespace kernel;

use kernel\Foundation\Router;
use kernel\App\Api\GetGSetController;
use kernel\App\Main as Main;

Router::get("_gset", GetGSetController::class);

//* 扩展相关
Router::view("_extensions", Main\Extensions\ExtensionListViewController::class);
Router::post("_extension/install", Main\Extensions\InstallExtensionController::class);
Router::post("_extension/upgrade", Main\Extensions\UpgradeExtensionController::class);
Router::post("_extension/uninstall", Main\Extensions\UninstallExtensionController::class);
Router::post("_extension/openClose", Main\Extensions\OpenCloseExtensionController::class);

//* IUU相关 I=install U=upgrade U=uninstall
Router::post("/system/init", Main\System\InitController::class);
Router::post("/system/upgrade", Main\System\UpgradeController::class);