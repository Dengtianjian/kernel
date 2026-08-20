<?php

use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Controller\Commands\MakeAppCommand;
use kernel\Controller\Commands\MakeControllerCommand;
use kernel\Controller\Commands\MakeModelCommand;
use kernel\Controller\Commands\MakeMiddlewareCommand;
use kernel\Controller\Commands\ScheduleRunCommand;

//* 测试专用
if (Config::get("mode") === "development") {
  Router::any("/", kernel\Controller\Main\IndexController::class);
}

//* 命令（CLI）：与 HTTP 路由统一在 Routes 注册；CLI 按命令名分发（不解析 URI），
//* HTTP 按 URI 匹配路由（不匹配命令）
Router::command("make:app", MakeAppCommand::class, "Create a new application skeleton");
Router::command("make:controller", MakeControllerCommand::class, "Create a new controller class");
Router::command("make:model", MakeModelCommand::class, "Create a new model class");
Router::command("make:middleware", MakeMiddlewareCommand::class, "Create a new middleware class");
Router::command("schedule:run", ScheduleRunCommand::class, "Run scheduled tasks (Crons/)");
