<?php

namespace kernel\Setup\Console;

use kernel\Foundation\Console\Console;
use kernel\Commands\MakeAppCommand;
use kernel\Commands\MakeControllerCommand;
use kernel\Commands\MakeModelCommand;
use kernel\Commands\MakeMiddlewareCommand;
use kernel\Commands\ScheduleRunCommand;

/**
 * 内核 CLI 装配类
 *
 * 在 kernel/console 入口通过 $console->setup(Bootstrap::class) 装配。
 * 构造方法接收 Console 实例，负责注册内核命令。
 */
class Bootstrap
{
  public function __construct(Console $console)
  {
    //* 注册内核命令
    $console
      ->register("make:app", MakeAppCommand::class, "Create a new application skeleton")
      ->register("make:controller", MakeControllerCommand::class, "Create a new controller class")
      ->register("make:model", MakeModelCommand::class, "Create a new model class")
      ->register("make:middleware", MakeMiddlewareCommand::class, "Create a new middleware class")
      ->register("schedule:run", ScheduleRunCommand::class, "Run scheduled tasks (Crons/)");
  }
}
