<?php

namespace kernel\Setup\Console;

use kernel\Foundation\Console\Console;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Commands\MakeAppCommand;
use kernel\Commands\MakeControllerCommand;
use kernel\Commands\MakeModelCommand;
use kernel\Commands\MakeMiddlewareCommand;
use kernel\Commands\ScheduleRunCommand;
use kernel\Commands\PackageCommand;
use kernel\Commands\UnpackCommand;

/**
 * 内核 CLI 装配类
 *
 * 在 kernel/console 入口通过 `new Console(Bootstrap::class)` 装配。
 * 构造不接收 Console 实例：Console 就是当前默认应用，用继承的 app() 回落取到，
 * 再调用其 addCommand() 注册内核命令。
 */
class Bootstrap extends AbilityBaseObject
{
  public function __construct()
  {
    //* 注册内核命令（app() 返回的是 Console 实例，command() 是 Console 的方法）
    $console = $this->app();
    $console
      ->addCommand("make:app", MakeAppCommand::class, "Create a new application skeleton")
      ->addCommand("make:controller", MakeControllerCommand::class, "Create a new controller class")
      ->addCommand("make:model", MakeModelCommand::class, "Create a new model class")
      ->addCommand("make:middleware", MakeMiddlewareCommand::class, "Create a new middleware class")
      ->addCommand("schedule:run", ScheduleRunCommand::class, "Run scheduled tasks (Crons/)")
      ->addCommand("package", PackageCommand::class, "Package isdtj and kernel into zip archives")
      ->addCommand("unpack", UnpackCommand::class, "Unpack isdtj.zip and kernel.zip into directories");
  }
}
