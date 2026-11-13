<?php

namespace kernel\Commands;

use kernel\Facades\Config;
use kernel\Foundation\FileSystem\Path;

/**
 * 创建新应用命令
 *
 * 命令名 make:app 在 kernel/console 中注册（Console::addCommand）。
 *
 * 用法：
 *   php kernel/console make:app <AppId>
 *
 * 生成应用骨架到 {root}/{AppId}/：Setup/（Bootstrap/Bootup/Shutdown 装配类）、Routes/index.php（HTTP 路由 + 命令注册）、
 * Controllers/、console 入口（Console 子类）等。
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
    $kernel = Path::kernel();
    $targetDirectory = $root . "/" . $AppId;
    if (is_dir($targetDirectory)) {
      $console->error("Directory already exists: {$targetDirectory}");
      return 1;
    }

    $mode = Config::get("mode", "production");

    mkdir($targetDirectory . "/Controllers", 0755, true);
    mkdir($targetDirectory . "/Routes", 0755, true);
    mkdir($targetDirectory . "/Setup", 0755, true);
    mkdir($targetDirectory . "/Data", 0755, true);
    mkdir($targetDirectory . "/Storage", 0755, true);

    //* 安装密钥：random_bytes 生成（不可预测），不用基于时间戳的 uniqid()
    file_put_contents($targetDirectory . "/Data/install.key", bin2hex(random_bytes(16)));

    //* 应用装配类：手动实例化配置、缓存、文件系统等组件
    file_put_contents(
      $targetDirectory . "/Setup/Bootstrap.php",
      <<<PHP
<?php

namespace {$AppId}\\Setup;
use kernel\\Foundation\\Config;
use kernel\\Foundation\\FileSystem\\FileSystem;
use kernel\\Foundation\\Middleware\\Middleware;
use kernel\\Foundation\\Object\\AbilityBaseObject;

/**
 * 应用装配类：按需实例化组件（App 构造只建容器与骨架，其余均为「访问即构建」）
 *
 * 在入口（index.php / console）作为构造第一个参数传入（构造即装配）：
 *   new App(\\{$AppId}\\Setup\\Bootstrap::class, "{$AppId}");
 * 构造末尾立即执行本构造方法（无参构造）：
 * - 应用实例不传参、不手动 \$this->app = \$app：继承自 BaseObject 的 \$this->app()
 *   会回落默认应用（构造时已把当前应用登记为默认）
 * - Config：不用手动 new，\$this->app()->config() 首次访问时自动读 Configs/ 目录
 * - new FileSystem：创建 Data/、Storage/ 等目录
 * - 缓存：不是每个应用都需要，要用就自己登记一行
 *   \$this->app()->provider(\kernel\Foundation\Builtin\Cache::class)；登记后
 *   \$this->app()->cache() 与门面 \kernel\Facades\Cache 都可用（动态 KEY 在请求级）
 * - new Middleware 后 ->set(...)：注册全局中间件，再 \$this->app()->provider(\$middleware) 登记
 * - \$this->app()->onBootUp() / ->onShutdown() / ->onError()：注册生命周期钩子，
 *   不必再 new Lifecycle，也不必手动登记（内部委托请求级的 Lifecycle）
 *
 * Request / Router / Middleware / Lifecycle / Config 都是访问即构建，不登记也能用；
 * 登记只是为了换成自己的实现（容器里的实例优先，登记的一定生效）：
 *   \$this->app()->provider(new \\kernel\\Foundation\\HTTP\\Request);
 *
 * 也可以传闭包（可获得 App 实例）：
 *   new App(function (\$app) {
 *     \$app->register(new \\kernel\\Foundation\\HTTP\\Request);
 *   });
 */
class Bootstrap extends AbilityBaseObject
{
  public function __construct()
  {
    //* 实例化文件系统（创建 Data/、Storage/ 等目录）
    new FileSystem;

    //* 中间件：手动 new Middleware 后注册，再登记进容器（如需启用全局中间件，取消注释）
    // \$middleware = new Middleware;
    // \$middleware->set(\\{$AppId}\\Middlewares\\GlobalAuthMiddleware::class);
    // \$this->app()->provider(\$middleware);

    //* 生命周期：直接挂在 App 上（内部委托请求级的 Lifecycle，随请求一起清）
    \$this->app()->onBootUp(\\{$AppId}\\Setup\\Bootup::class);
    \$this->app()->onShutdown(\\{$AppId}\\Setup\\Shutdown::class);
  }
}
PHP
    );

    //* HTTP 入口
    file_put_contents(
      $targetDirectory . "/index.php",
      <<<PHP
<?php
include_once("{$kernel}/vendor/autoload.php");

use kernel\\Foundation\\App;

//* 构造即装配：第一个参数是装配类，构造返回后应用即可 run()
\$app = new App(\\{$AppId}\\Setup\\Bootstrap::class, "{$AppId}");
\$app->run();
PHP
    );

    //* 路由文件（HTTP 路由）
    file_put_contents(
      $targetDirectory . "/Routes/index.php",
      <<<PHP
<?php
use kernel\\Foundation\\Router\\Route;

Route::get("/", {$AppId}\\Controllers\\IndexController::class);
PHP
    );

    //* 引导/关闭装配类
    file_put_contents(
      $targetDirectory . "/Setup/Bootup.php",
      <<<PHP
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

    file_put_contents(
      $targetDirectory . "/Setup/Shutdown.php",
      <<<PHP
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

    //* CLI 入口（Console 子类，可补充 addCommand() 注册额外命令）
    file_put_contents(
      $targetDirectory . "/console",
      <<<PHP
#!/usr/bin/env php
<?php
include_once("{$kernel}/vendor/autoload.php");

use kernel\\Foundation\\Console\\Console;

//* 命令由 Console 统一注册（不再经 Router）
//* 构造即装配：第一个参数是装配类，在内核命令之外追加本应用命令
\$console = new Console(\\{$AppId}\\Setup\\Bootstrap::class, "{$AppId}");
\$console->run();
PHP
    );

    //* 示例控制器
    file_put_contents(
      $targetDirectory . "/Controllers/IndexController.php",
      <<<PHP
<?php

namespace {$AppId}\\Controllers;
use kernel\\Foundation\\Controller\\Controller;

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
