<?php

namespace kernel\Controller\Console;
use kernel\Foundation\FileSystem\FileSystem;

use kernel\Foundation\App;

/**
 * 生成中间件命令
 *
 * 命令名 make:middleware 在 kernel/Routes/index.php 中注册（Router::command）。
 *
 * 用法：
 *   php kernel/console make:middleware Auth             // 生成 AuthMiddleware
 *   php kernel/console make:middleware Admin/Auth       // 生成 Admin/AuthMiddleware（子命名空间）
 *   php kernel/console make:middleware Auth --force     // 覆盖已存在文件
 *
 * 骨架参照 kernel\Foundation\Middleware\Middleware 基类约定。
 */
class MakeMiddlewareCommand extends MakeCommand
{
  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 中间件名（如 "Auth" 或 "Admin/Auth"）
   * @param array $options 选项参数：force 是否覆盖已存在文件
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $name = $args[0] ?? "";
    if ($name === "") {
      $console->error("Usage: make:middleware <MiddlewareName>");
      $console->info("Example: make:middleware Auth  or  make:middleware Admin/Auth");
      return 1;
    }

    [$subDir, $shortName] = $this->split($name);
    $className = $shortName . "Middleware";
    $classPath = $subDir !== "" ? $subDir . "/" . $className : $className;
    $namespace = $this->joinNamespace(App::id() . "\\Middleware", $subDir);

    $body = <<<PHP
use kernel\\Foundation\\Middleware\\Middleware;
use kernel\\Foundation\\HTTP\\Request;

/**
 * {$shortName} 中间件
 */
class {$className} extends Middleware
{
  /**
   * 中间件处理入口
   *
   * @param Request \$R 请求实例
   * @return mixed 返回 null 表示放行，返回其他值（如 Response/ReturnList）表示中断
   */
  public function data(Request \$R)
  {

  }
}
PHP;

    return $this->write(FileSystem::root() . "/Middleware", $classPath, $namespace, $body, !empty($options["force"]), $console) ? 0 : 1;
  }
}
