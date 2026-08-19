<?php

namespace kernel\Controller\Console;
use kernel\Foundation\FileSystem\FileSystem;

use kernel\Foundation\Config;

/**
 * 创建新应用命令
 *
 * 命令名 make:app 在 kernel/Routes/index.php 中注册（Router::command）。
 *
 * 用法：
 *   php kernel/console make:app <AppId>
 *
 * 生成应用骨架到 {root}/{AppId}/：Routes/index.php（HTTP 路由 + 命令注册）、
 * Controller/、Lifecycle/、console 入口（Console 子类）等。
 */
class MakeAppCommand
{
  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 应用 ID（如 "test"、"isdtj"）
   * @param array $options 选项参数（无）
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $AppId = trim($args[0] ?? "");
    if ($AppId === "") {
      $console->error("Usage: make:app <AppId>");
      $console->info("Example: make:app hello");
      return 1;
    }

    if (!preg_match("/^[a-zA-Z][a-zA-Z0-9_]*$/", $AppId)) {
      $console->error("AppId \"{$AppId}\" is invalid. Only letters, numbers and underscores are allowed, and must start with a letter.");
      return 1;
    }

    $root = FileSystem::root();
    $targetDirectory = $root . "/" . $AppId;
    if (is_dir($targetDirectory)) {
      $console->error("Directory already exists: {$targetDirectory}");
      return 1;
    }

    $mode = Config::get("mode", "production");

    mkdir($targetDirectory . "/Controller", 0755, true);
    mkdir($targetDirectory . "/Routes", 0755, true);
    mkdir($targetDirectory . "/Lifecycle", 0755, true);
    mkdir($targetDirectory . "/Data", 0755, true);
    mkdir($targetDirectory . "/Storage", 0755, true);

    //* HTTP 入口
    file_put_contents($targetDirectory . "/index.php", <<<PHP
<?php
include_once("../kernel/vendor/autoload.php");

use kernel\\Foundation\\App;

\$app = new App("{$AppId}");
\$app->run();
PHP
    );

    //* 路由文件（HTTP 路由 + 命令注册）
    file_put_contents($targetDirectory . "/Routes/index.php", <<<PHP
<?php
use kernel\\Foundation\\Router;

Router::get("/", {$AppId}\\Controller\\IndexController::class);

//* 命令在 Routes 中注册（与 HTTP 路由统一）：CLI 按命令名分发，不解析 URI
//* 例：Router::command("app:hello", {$AppId}\\Controller\\HelloCommand::class, "Say hello");
PHP
    );

    //* 生命周期装配类
    file_put_contents($targetDirectory . "/Lifecycle/Bootup.php", <<<PHP
<?php

namespace {$AppId}\\Lifecycle;
use kernel\\Foundation\\HTTP\\Request;

/**
 * 启动装配类：请求处理开始前执行（HTTP 与 CLI 均执行）
 */
class Bootup
{
  public function __construct(?Request \$R = null)
  {

  }
}
PHP
    );

    file_put_contents($targetDirectory . "/Lifecycle/Shutdown.php", <<<PHP
<?php

namespace {$AppId}\\Lifecycle;
use kernel\\Foundation\\HTTP\\Response;

/**
 * 关闭装配类：请求处理结束后执行（HTTP 与 CLI 均执行）
 */
class Shutdown
{
  public function __construct(?Response \$response = null)
  {

  }
}
PHP
    );

    //* CLI 入口（Console 子类，可补充 register() 注册额外命令）
    file_put_contents($targetDirectory . "/console", <<<PHP
#!/usr/bin/env php
<?php
include_once("../kernel/vendor/autoload.php");

use kernel\\Foundation\\Console\\Console;

//* 命令统一在 Routes/index.php 中注册（Router::command），无需在此重复注册
//* 如需补充实例级命令，可在此 \$console->register("name", fn) 注册
\$console = new Console("{$AppId}");
\$console->run();
PHP
    );

    //* 示例控制器
    file_put_contents($targetDirectory . "/Controller/IndexController.php", <<<PHP
<?php

namespace {$AppId}\\Controller;
use kernel\\Foundation\\Controller\\Controller;
use kernel\\Foundation\\HTTP\\Request;

/**
 * 默认控制器
 */
class IndexController extends Controller
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
    return [
      "message" => "Hello, {$AppId}!"
    ];
  }
}
PHP
    );

    $console->success("Application \"{$AppId}\" created at {$targetDirectory}");
    $console->info("Mode: {$mode}");
    return 0;
  }
}
