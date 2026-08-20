<?php

namespace kernel\Controller\Commands;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\Config;

/**
 * 创建新应用命令
 *
 * 命令名 make:app 在 kernel/Routes/index.php 中注册（Router::command）。
 *
 * 用法：
 *   php kernel/console make:app <AppId>
 *
 * 生成应用骨架到 {root}/{AppId}/：Setup/（Bootstrap/Bootup/Shutdown 装配类）、Routes/index.php（HTTP 路由 + 命令注册）、
 * Controller/、console 入口（Console 子类）等。
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

    $root = Path::root();
    $kernelRoot = Path::kernelRoot();
    $targetDirectory = $root . "/" . $AppId;
    if (is_dir($targetDirectory)) {
      $console->error("Directory already exists: {$targetDirectory}");
      return 1;
    }

    $mode = Config::get("mode", "production");

    mkdir($targetDirectory . "/Controller", 0755, true);
    mkdir($targetDirectory . "/Routes", 0755, true);
    mkdir($targetDirectory . "/Setup", 0755, true);
    mkdir($targetDirectory . "/Data", 0755, true);
    mkdir($targetDirectory . "/Storage", 0755, true);

    //* 应用装配类：手动实例化配置、缓存、文件系统等组件
    file_put_contents($targetDirectory . "/Setup/Bootstrap.php", <<<PHP
<?php

namespace {$AppId}\\Setup;
use kernel\\Foundation\\Cache;
use kernel\\Foundation\\Config;
use kernel\\Foundation\\FileSystem\\FileSystem;
use kernel\\Foundation\\Lifecycle;
use kernel\\Foundation\\Middleware\\Middleware;
use kernel\\Foundation\\Router;

/**
 * 应用装配类：手动实例化组件（App 构造不再自动实例化任何组件，均为延迟实例化）
 *
 * 在入口（index.php / console）通过 \$app->setup(\\{$AppId}\\Setup\\Bootstrap::class) 装配，
 * setup() 调用时立即执行本构造方法（构造参数为当前 App 实例 \$app）：
 * - new Config：加载 Configs/ 目录配置（框架兜底：run() 前未加载会自动加载）
 * - new FileSystem：创建 Data/、Storage/ 等目录
 * - new Cache：生成缓存动态 KEY（Cache::key()）
 * - new Middleware 后 ->set(...)：注册全局中间件，再 \$app->set(["middleware" => \$middleware]) 注入
 * - new Lifecycle 后 ->onBootUp() / ->onShutdown() 等：注册生命周期钩子，
 *   再 \$app->set(["lifeCycle" => \$lifeCycle]) 注入
 *
 * 如需自定义组件实例（如 Router 后续增加构造参数，可自行实例化并传参）：
 *   \$router = new Router("http");
 *   \$app->set(["router" => \$router]);
 *
 * 也可以在 setup() 中传闭包（推荐，可获得 App 实例）：
 *   \$app->setup(function (\$app) {
 *     \$app->set(["router" => new Router("http")]);
 *   });
 */
class Bootstrap
{
  public function __construct(\$app)
  {
    //* 初始化配置（加载 Configs/ 目录下的配置文件）
    new Config;

    //* 实例化文件系统（创建 Data/、Storage/ 等目录）
    new FileSystem;

    //* 实例化缓存（生成缓存动态 KEY）
    new Cache;

    //* 中间件：手动 new Middleware 后注册，再注入 App（如需启用全局中间件，取消注释）
    // \$middleware = new Middleware;
    // \$middleware->set(\\{$AppId}\\Middleware\\GlobalAuthMiddleware::class);
    // \$app->set(["middleware" => \$middleware]);

    //* 生命周期：手动 new Lifecycle 后注册钩子，再注入 App
    \$lifeCycle = new Lifecycle;
    \$lifeCycle->onBootUp(\\{$AppId}\\Setup\\Bootup::class);
    \$lifeCycle->onShutdown(\\{$AppId}\\Setup\\Shutdown::class);
    \$app->set(["lifeCycle" => \$lifeCycle]);
  }
}
PHP
    );

    //* HTTP 入口
    file_put_contents($targetDirectory . "/index.php", <<<PHP
<?php
include_once("{$kernelRoot}/vendor/autoload.php");

use kernel\\Foundation\\App;

\$app = new App("{$AppId}");
//* 应用装配：手动实例化配置、缓存、文件系统等（setup() 必须在 run() 之前调用）
\$app->setup(\\{$AppId}\\Setup\\Bootstrap::class);
\$app->run();
PHP
    );

    //* 路由文件（HTTP 路由 + 命令注册）
    file_put_contents($targetDirectory . "/Routes/index.php", <<<PHP
<?php
use kernel\\Foundation\\Router;

Router::get("/", {$AppId}\\Controller\\IndexController::class);

//* 命令在 Routes 中注册（与 HTTP 路由统一）：CLI 按命令名分发，不解析 URI
//* 例：Router::command("app:hello", {$AppId}\\Controller\\Commands\\HelloCommand::class, "Say hello");
PHP
    );

    //* 引导/关闭装配类
    file_put_contents($targetDirectory . "/Setup/Bootup.php", <<<PHP
<?php

namespace {$AppId}\\Setup;
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

    file_put_contents($targetDirectory . "/Setup/Shutdown.php", <<<PHP
<?php

namespace {$AppId}\\Setup;
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
include_once("{$kernelRoot}/vendor/autoload.php");

use kernel\\Foundation\\Console\\Console;

//* 命令统一在 Routes/index.php 中注册（Router::command），无需在此重复注册
//* 如需补充实例级命令，可在此 \$console->register("name", fn) 注册
\$console = new Console("{$AppId}");
//* 应用装配（setup() 必须在 run() 之前调用）
\$console->setup(\\{$AppId}\\Setup\\Bootstrap::class);
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
   * @return mixed 响应数据、Response 或 Result 等
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
