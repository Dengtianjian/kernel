<?php

namespace kernel\Commands;

/**
 * 生成中间件命令
 *
 * 用法：
 *   php kernel/console make:middleware Auth          // 生成 AuthMiddleware
 *   php kernel/console make:middleware Admin/Auth    // 生成 Admin/AuthMiddleware（子命名空间）
 *   php kernel/console make:middleware Auth --force  // 覆盖已存在文件
 *
 * 骨架参照 kernel\Foundation\Middleware 基类约定。
 */
class MakeMiddlewareCommand extends MakeCommand
{
  /** @var string 命令名 */
  protected $name = "make:middleware";

  /** @var string 命令说明 */
  protected $description = "Create a new middleware class";

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
    $namespace = $this->joinNamespace(F_APP_ID . "\\Middleware", $subDir);

    $body = <<<PHP
use kernel\\Foundation\\Middleware;

/**
 * {$shortName} 中间件
 */
class {$className} extends Middleware
{
  /**
   * 中间件处理逻辑
   *
   * @param \\Closure \$next 下一个处理闭包
   * @return mixed 返回响应对象或调用 \$next() 继续后续处理
   */
  public function handle(\\Closure \$next)
  {
    // TODO: 在此编写中间件逻辑

    return \$next();
  }
}
PHP;

    return $this->write(F_APP_ROOT . "/Middleware", $classPath, $namespace, $body, !empty($options["force"]), $console) ? 0 : 1;
  }
}
