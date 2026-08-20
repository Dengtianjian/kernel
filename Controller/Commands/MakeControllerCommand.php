<?php

namespace kernel\Controller\Commands;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\App;

/**
 * 生成控制器命令
 *
 * 命令名 make:controller 在 kernel/Routes/index.php 中注册（Router::command）。
 *
 * 用法：
 *   php kernel/console make:controller User          // 生成 UserController
 *   php kernel/console make:controller Admin/User    // 生成 Admin/UserController（子命名空间）
 *   php kernel/console make:controller User --force  // 覆盖已存在文件
 *
 * 骨架参照 kernel\Controller 下现有控制器约定（继承 Controller 基类）。
 */
class MakeControllerCommand extends MakeCommand
{
  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 控制器名（如 "User" 或 "Admin/User"）
   * @param array $options 选项参数：force 是否覆盖已存在文件
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $name = $args[0] ?? "";
    if ($name === "") {
      $console->error("Usage: make:controller <ControllerName>");
      $console->info("Example: make:controller User  or  make:controller Admin/User");
      return 1;
    }

    [$subDir, $shortName] = $this->split($name);
    $className = $shortName . "Controller";
    $classPath = $subDir !== "" ? $subDir . "/" . $className : $className;
    $namespace = $this->joinNamespace(App::id() . "\\Controller", $subDir);

    $body = <<<PHP
use kernel\\Foundation\\Controller\\Controller;
use kernel\\Foundation\\HTTP\\Request;

/**
 * {$shortName} 控制器
 */
class {$className} extends Controller
{
  public function __construct(Request \$R)
  {
    parent::__construct(\$R);
  }

  /**
   * 业务处理入口
   *
   * @return mixed 响应数据、Response 或 ReturnList 等
   */
  public function data()
  {

  }
}
PHP;

    return $this->write(Path::root() . "/Controller", $classPath, $namespace, $body, !empty($options["force"]), $console) ? 0 : 1;
  }
}
