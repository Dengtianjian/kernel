<?php

namespace kernel\Commands;

/**
 * 创建应用命令
 *
 * 在 kernel 同级目录（项目根）创建指定名称的应用骨架：
 * - console：应用 CLI 入口
 * - Configs/Config.php：应用配置（version、mode）
 * - Controller / Middleware / Model / Routes / Service / Storage / Data 目录
 * - Controller/IndexController.php：示例控制器
 * - Routes/index.php：路由入口
 * - index.php：应用 HTTP 入口
 * - README.md、install.key（随机 16 位字符串）、composer.json（PSR-4 自动加载）
 *
 * 用法：
 *   php {应用}/console make:app myapp
 */
class MakeAppCommand
{
  /** @var string 命令名 */
  protected $name = "make:app";

  /** @var string 命令说明 */
  protected $description = "Create a new application skeleton";

  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 应用名
   * @param array $options 选项参数
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $name = $args[0] ?? "";
    if ($name === "" || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
      $console->error("Usage: make:app <AppName>");
      $console->info("Example: make:app myapp");
      return 1;
    }

    //* 应用创建在 kernel 同级目录（项目根目录）
    $appRoot = F_ROOT . DIRECTORY_SEPARATOR . $name;
    if (file_exists($appRoot)) {
      $console->error("Application already exists: {$appRoot}");
      return 1;
    }

    //* 目录骨架
    $directories = ["Configs", "Controller", "Middleware", "Model", "Routes", "Service", "Storage", "Data"];
    foreach ($directories as $dir) {
      mkdir($appRoot . DIRECTORY_SEPARATOR . $dir, 0755, true);
    }

    //* 安装密钥（随机 16 位十六进制字符串）
    $installKey = bin2hex(random_bytes(8));
    //* 应用版本与运行模式
    $version = "0.1.0." . date("Ymd.Hi");
    $mode = "production";

    //* 文件骨架
    $files = [];

    //* console：应用 CLI 入口
    $files["console"] = <<<PHP
#!/usr/bin/env php
<?php

/**
 * {$name} CLI 入口
 * 用法：php {$name}/console <command> [options] [arguments]
 */

//* 定位 autoload：优先内核的 vendor（提供 kernel\ 命名空间），应用自身 vendor 其次
\$kernelVendor = dirname(__DIR__) . "/kernel/vendor/autoload.php";
\$appVendor = __DIR__ . "/vendor/autoload.php";

if (!file_exists(\$kernelVendor)) {
  fwrite(STDERR, "kernel autoload.php not found. Please run composer install in kernel.\n");
  exit(1);
}

include_once(\$kernelVendor);

//* 应用自身的 vendor（提供 {$name}\ 命名空间与第三方依赖）
if (file_exists(\$appVendor)) {
  include_once(\$appVendor);
}

use kernel\\Foundation\\Console\\Console;

//* 构造时已自动发现内核与当前应用的 Commands/ 目录命令类
\$console = new Console("{$name}");

//* 注册额外命令（非 Commands/ 目录的命令可在此手动注册或 discover）
// \$console->register("my:cmd", function (\$console, \$args, \$options) { return 0; });

//* 执行命令分发，并以退出码结束（Console::run 内部调用 handle 后 exit）
\$console->run();
PHP;

    //* Configs/Config.php：应用配置（version、mode）
    $files["Configs/Config.php"] = <<<PHP
<?php

namespace {$name}\\Configs;

return [
  "version" => "{$version}",
  "mode" => "{$mode}",
];
PHP;

    //* Controller/IndexController.php：示例控制器
    $files["Controller/IndexController.php"] = <<<PHP
<?php

namespace {$name}\\Controller;

use kernel\\Foundation\\Controller\\Controller;
use kernel\\Foundation\\HTTP\\Request;

class IndexController extends Controller
{
  public function __construct(Request \$R)
  {
    parent::__construct(\$R);
  }

  /**
   * 业务处理入口
   *
   * @return mixed 响应数据、Response 或 ReturnResult 等
   */
  public function data()
  {
    return "Hello {$name}!";
  }
}
PHP;

    //* Routes/index.php：路由入口
    $files["Routes/index.php"] = <<<PHP
<?php

namespace {$name};

use kernel\\Foundation\\Router;

Router::get("/", {$name}\\Controller\\IndexController::class);
PHP;

    //* index.php：应用 HTTP 入口
    $files["index.php"] = <<<PHP
<?php

use kernel\\Foundation\\App;

include_once("../kernel/index.php");

\$app = new App("{$name}");

// \$app->setMiddleware(\\{$name}\\Middleware\\GlobalAuthMiddleware::class);

\$app->run();
PHP;

    //* README.md
    $files["README.md"] = <<<MD
# {$name}

PHP 要求 8.X

## 安装

```bash
# 生成 autoload（自动加载 `{$name}\\` 命名空间）
composer dump-autoload
```

## 开发

```bash
# CLI 命令
php {$name}/console --help
```
MD;

    //* install.key：安装密钥
    $files["install.key"] = $installKey . "\n";

    //* composer.json：自动写入 PSR-4 加载规则
    $files["composer.json"] = json_encode([
      "name" => $name . "/backend",
      "type" => "project",
      "autoload" => [
        "psr-4" => [
          $name . "\\" => ""
        ]
      ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    foreach ($files as $relativePath => $content) {
      file_put_contents($appRoot . DIRECTORY_SEPARATOR . $relativePath, $content);
    }

    //* console 添加可执行权限
    chmod($appRoot . DIRECTORY_SEPARATOR . "console", 0755);

    //* 返回生成结果数组
    $console->success("Application created: {$appRoot}");
    $console->line("");
    print_r([
      "app" => $name,
      "version" => $version,
      "mode" => $mode,
      "install_key" => $installKey,
      "directories" => $directories,
      "files" => array_keys($files),
    ]);

    return 0;
  }
}
