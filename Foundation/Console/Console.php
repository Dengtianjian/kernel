<?php

namespace kernel\Foundation\Console;

use kernel\Foundation\App;
use kernel\Foundation\Config;

/**
 * 控制台应用
 *
 * 继承 App：提供命令注册（register/discover）、分发、参数解析、彩色输出与交互输入能力。
 * 命令完全由本类实例管理，不再依赖 Router（Router 只负责 HTTP 路由）。
 *
 * 命令注册方式：
 * 1. 业务方在自己入口（如 kernel/console、App 的 CLI 入口）手动 Console::register() 注册
 * 2. discover() 自动扫描命令类目录注册（命令类自带 $name 属性）
 *
 * 命令处理器支持三种形式：
 * 1. 命令控制器类：实现 handle(Console $console, array $args, array $options): int 方法，
 *    类放 Controller/ 目录（如 "kernel\\Controller\\Commands\\MakeAppCommand"），由本类实例化并调用
 * 2. 闭包：function (Console $console, array $args, array $options): int
 *
 * 命令名支持冒号命名空间（如 make:controller）；输入空参数、help、-h 或 --help
 * 时自动列出全部已注册命令。CLI 环境下异常/错误输出到 stderr 并以非 0 退出。
 *
 * 入口脚本：kernel/console
 *   $console = new Console("kernel");
 *   $console->run(); // 内部调用 handle() 分发命令并以退出码结束
 *
 * 命令行调用：php kernel/console make:app hello --name=john
 */
class Console extends App
{
  /**
   * 已注册的命令
   * @var array<string, array{handler: callable|string, description: string}>
   */
  protected $commands = [];
  /**
   * 命令行参数（不含脚本名）
   * @var array
   */
  protected $argv = [];
  /**
   * 当前命令名
   * @var string
   */
  protected $commandName = "";
  /**
   * 位置参数
   * @var array
   */
  protected $arguments = [];
  /**
   * 选项参数
   * @var array
   */
  protected $options = [];

  /**
   * 构建
   *
   * @param string $AppId AppId，默认 kernel
   * @param string $KernelId KernelId，默认 kernel
   */
  function __construct($AppId = "kernel", $KernelId = "kernel")
  {
    //* Router 由 App 持有（ensureInstances()/router() 实例化，只负责 HTTP 路由；命令由本类管理）
    parent::__construct($AppId, $KernelId);

    $this->argv = isset($GLOBALS['argv']) ? array_slice($GLOBALS['argv'], 1) : [];

    //* getApp() 在父类 App 构造时已注册当前实例（App::getInstance()）

    //* CLI 环境覆盖异常处理：直接输出到 stderr 并以非 0 退出
    \set_exception_handler(function ($exception) {
      $message = $exception->getMessage();
      $file = $exception->getFile();
      $line = $exception->getLine();
      $this->error($message ?: get_class($exception));
      if (Config::get("mode") !== "production") {
        $this->line("  at {$file}:{$line}", "90");
      }
      exit(1);
    });
    \set_error_handler(function ($code, $message, $file, $line) {
      if (!(error_reporting() & $code)) {
        return false;
      }
      $this->warning($message);
      $this->line("  at {$file}:{$line}", "90");
      return true;
    }, E_ALL);
  }
  /**
   * 注册命令
   *
   * @param string $name 命令名，例如 "make:controller"，支持冒号命名空间
   * @param callable|string $handler 闭包：function (Console $console, array $args, array $options): int；
   *                                 或命令类名：类需实现 handle(Console, array, array): int 方法
   * @param string $description 命令说明，用于帮助列表
   * @return Console
   */
  public function register(string $name, $handler, string $description = ""): Console
  {
    $this->commands[$name] = [
      "handler" => $handler,
      "description" => $description
    ];
    return $this;
  }
  /**
   * 从目录自动发现并注册命令类
   *
   * 扫描指定目录下所有 .php 文件，按 PSR-4 约定推断类名（命名空间 + 文件名），
   * 类存在且定义了 $name 属性（命令名）即注册，$description 属性作为命令说明。
   *
   * 命令类约定：
   *   namespace App\Commands;
   *   class CacheClearCommand
   *   {
   *     /** @var string 命令名，例如 "cache:clear" *\/
   *     protected $name = "cache:clear";
   *     /** @var string 命令说明，用于帮助列表 *\/
   *     protected $description = "Clear application cache";
   *
   *     public function handle($console, $args, $options): int { ... }
   *   }
   *
   * @param string $directory 命令类所在目录（绝对路径），目录不存在时静默跳过
   * @param string $namespace 命令类命名空间，类名取文件名（如 "App\\Commands"）
   * @return Console
   */
  public function discover(string $directory, string $namespace): Console
  {
    if (!is_dir($directory)) {
      return $this;
    }
    $files = glob(rtrim($directory, "/") . "/*.php");
    if ($files === false) {
      return $this;
    }
    foreach ($files as $file) {
      $className = $namespace . "\\" . basename($file, ".php");
      if (!class_exists($className)) {
        continue;
      }
      $properties = (new \ReflectionClass($className))->getDefaultProperties();
      $name = $properties["name"] ?? null;
      if (!is_string($name) || $name === "") {
        continue;
      }
      $this->register($name, $className, (string) ($properties["description"] ?? ""));
    }
    return $this;
  }
  /**
   * 获取已注册的全部命令
   *
   * 命令由本实例 register()/discover() 统一管理，不再依赖 Router。
   *
   * @return array<string, array> 命令名 => 命令定义
   */
  public function commands(): array
  {
    return $this->commands;
  }
  /**
   * 当前命令名
   *
   * @return string
   */
  public function command(): string
  {
    return $this->commandName;
  }
  /**
   * 获取位置参数
   *
   * @param integer $index 参数下标，从 0 开始
   * @param mixed $default 不存在时返回的默认值
   * @return mixed
   */
  public function argument(int $index, $default = null)
  {
    return $this->arguments[$index] ?? $default;
  }
  /**
   * 获取选项值
   * 支持 --key=value、--key value、-k value、--flag（布尔 true）
   *
   * @param string $name 选项名
   * @param mixed $default 不存在时返回的默认值
   * @return mixed
   */
  public function option(string $name, $default = null)
  {
    return $this->options[$name] ?? $default;
  }
  /**
   * 分发执行命令
   *
   * 分发前触发 App 生命周期"启动"钩子（bootUp），命令执行完毕（正常或异常）后触发
   * "结束"钩子（shutdown，由 App::$lifeCycle 触发），供命令在统一时机做初始化与清理。
   * 命令抛出的异常会先执行结束钩子（记录进度、释放资源），再交由 CLI 异常处理器输出并退出。
   *
   * @param array|null $argv 命令行参数（不含脚本名）。不传时使用构造时捕获的 GLOBALS['argv']
   * @return integer 退出码
   */
  public function handle(?array $argv = null): int
  {
    //* 延迟实例化兜底：setup() 未注入的组件在此自动实例化（Request/Middleware/Lifecycle/Config）
    $this->ensureInstances();

    if ($argv === null) {
      $argv = $this->argv;
    }

    //* 解析选项与位置参数
    [$name, $arguments, $options] = $this->parseArguments($argv);

    $this->commandName = $name;
    $this->arguments = $arguments;
    $this->options = $options;

    //* CLI 下 Request 的 URI 即命中的命令名（HTTP 下为请求 URI）
    $this->request->uri($name);

    //* 调用生命周期"启动"钩子
    $this->lifeCycle->fireBootUp();

    $exitCode = 0;
    try {
      //* 帮助
      if ($name === "" || $name === "help" || $name === "-h" || $name === "--help") {
        $exitCode = $this->listCommands();
      } elseif (!isset($this->commands[$name])) {
        $this->error("Command \"{$name}\" is not defined.");
        $this->line("");
        $exitCode = $this->listCommands(1);
      } else {
        //* CLI 按命令名匹配（仅查本实例 register()/discover() 注册的命令）
        $exitCode = $this->execute($this->commands[$name]);
      }

      //* 调用生命周期"结束"钩子
      $this->lifeCycle->fireShutdown($exitCode, [
        "exception" => null,
        "error" => false
      ]);
    } catch (\Throwable $E) {
      //* 命令执行异常：先触发错误钩子（onError），再执行结束钩子（记录已执行到的步骤、释放资源），最后交由异常处理器输出并退出
      $this->lifeCycle->fireError($E);
      $this->lifeCycle->fireShutdown($exitCode, [
        "exception" => $E,
        "error" => true
      ]);
      throw $E;
    }

    return $exitCode;
  }
  /**
   * 解析命令行参数
   * 第一个非选项参数作为命令名，其余为位置参数；选项支持 --key=value、--key value、-k value、--flag
   *
   * @param array $argv 参数数组
   * @return array [命令名, 位置参数, 选项]
   */
  protected function parseArguments(array $argv): array
  {
    $name = "";
    $arguments = [];
    $options = [];
    $count = count($argv);

    for ($i = 0; $i < $count; $i++) {
      $arg = $argv[$i];

      if ($name === "" && !$this->isOption($arg)) {
        $name = $arg;
        continue;
      }

      if ($this->isOption($arg)) {
        if (strpos($arg, "=") !== false) {
          [$key, $value] = explode("=", substr($arg, strpos($arg, "-") + 1) , 2);
          $options[trim($key, "-")] = $value;
        } else {
          $key = trim($arg, "-");
          //* --key value / -k value 形式，下一个参数作为值
          if ($this->isOptionValue($argv, $i + 1)) {
            $options[$key] = $argv[$i + 1];
            $i++;
          } else {
            $options[$key] = true;
          }
        }
        continue;
      }

      $arguments[] = $arg;
    }

    return [$name, $arguments, $options];
  }
  /**
   * 判断参数是否为选项形式
   *
   * @param string $arg
   * @return bool
   */
  protected function isOption(string $arg): bool
  {
    return strlen($arg) > 1 && $arg[0] === "-";
  }
  /**
   * 判断下一个参数是否可作为选项的值（非选项形式）
   *
   * @param array $argv
   * @param integer $nextIndex
   * @return bool
   */
  protected function isOptionValue(array $argv, int $nextIndex): bool
  {
    return isset($argv[$nextIndex]) && !$this->isOption($argv[$nextIndex]);
  }
  /**
   * 执行命令处理器
   *
   * 命令由本实例 register()/discover() 注册，定义结构为：
   * - 闭包：function (Console $console, array $args, array $options): int
   * - 命令类名（默认调用 handle() 方法）
   *
   * @param array $command 命令定义
   * @return integer 退出码
   */
  protected function execute(array $command): int
  {
    $handler = $command["handler"];

    if (is_string($handler) && class_exists($handler)) {
      //* 类名处理器：实例化后调用 handle 方法
      $instance = new $handler();
      if (!method_exists($instance, "handle")) {
        $this->error("Command class {$handler} must define a handle() method.");
        return 1;
      }
      $result = $instance->handle($this, $this->arguments, $this->options);
    } else {
      //* 闭包处理器
      $result = call_user_func($handler, $this, $this->arguments, $this->options);
    }

    return is_int($result) ? $result : 0;
  }
  /**
   * 输出帮助：列出所有已注册命令
   *
   * @param integer $exitCode 返回的退出码，默认 0（help 场景），命令不存在时传 1
   * @return integer 退出码
   */
  protected function listCommands(int $exitCode = 0): int
  {
    $this->line("Usage:", "33");
    $this->line("  console <command> [options] [arguments]");
    $this->line("");

    $commands = $this->commands();
    if (empty($commands)) {
      $this->warning("No commands registered.");
      return 0;
    }

    $this->line("Available commands:", "33");
    $names = array_keys($commands);
    $maxLength = max(array_map("strlen", $names));
    ksort($commands);
    foreach ($commands as $name => $command) {
      $description = $command["description"] ? "  " . $command["description"] : "";
      $this->line("  " . str_pad($name, $maxLength + 2) . $description);
    }
    $this->line("");
    $this->line("Options:", "33");
    $this->line("  " . str_pad("-h, --help", $maxLength + 2) . "Display help");

    return $exitCode;
  }

  /* ==================== 输出 ==================== */
  /**
   * 输出一行文本
   *
   * @param string $text 文本
   * @param string|null $color ANSI 颜色码，null 表示无色。例如 "32" 绿色
   * @param resource|null $stream 输出流，默认 STDOUT
   * @return Console
   */
  public function line(string $text = "", ?string $color = null, $stream = null): Console
  {
    if ($stream === null) {
      $stream = STDOUT;
    }
    if ($color && $this->supportsColor()) {
      $text = "\033[{$color}m{$text}\033[0m";
    }
    fwrite($stream, $text . PHP_EOL);
    return $this;
  }
  /**
   * 绿色成功输出
   *
   * @param string $text
   * @return Console
   */
  public function success(string $text): Console
  {
    return $this->line($text, "32");
  }
  /**
   * 青色信息输出
   *
   * @param string $text
   * @return Console
   */
  public function info(string $text): Console
  {
    return $this->line($text, "36");
  }
  /**
   * 黄色警告输出
   *
   * @param string $text
   * @return Console
   */
  public function warning(string $text): Console
  {
    return $this->line($text, "33");
  }
  /**
   * 红色错误输出，写入 STDERR
   *
   * @param string $text
   * @return Console
   */
  public function error(string $text): Console
  {
    return $this->line($text, "31", STDERR);
  }
  /**
   * 是否支持 ANSI 颜色（TTY 且未设置 NO_COLOR）
   *
   * @return bool
   */
  protected function supportsColor(): bool
  {
    if (getenv("NO_COLOR") !== false) {
      return false;
    }
    return function_exists("stream_isatty") && @stream_isatty(STDOUT);
  }

  /* ==================== 交互输入 ==================== */
  /**
   * 提示输入
   *
   * @param string $question 问题
   * @param mixed $default 默认值，回车直接使用
   * @return string
   */
  public function ask(string $question, $default = null): string
  {
    $suffix = $default !== null ? " [" . $default . "]" : "";
    fwrite(STDOUT, $question . $suffix . ": ");
    $input = trim(fgets(STDIN));
    if ($input === "" && $default !== null) {
      return $default;
    }
    return $input;
  }
  /**
   * 确认询问，返回布尔值
   *
   * @param string $question 问题
   * @param boolean $default 默认值
   * @return bool
   */
  public function confirm(string $question, bool $default = true): bool
  {
    $hint = $default ? "Y/n" : "y/N";
    $answer = strtolower($this->ask($question . " ({$hint})"));
    if ($answer === "") {
      return $default;
    }
    return in_array($answer, ["y", "yes"]);
  }
  /**
   * 静默输入（不回显），用于密码等敏感信息
   *
   * @param string $question 问题
   * @return string
   */
  public function secret(string $question): string
  {
    fwrite(STDOUT, $question . ": ");
    if (stripos(PHP_OS, "WIN") !== 0) {
      //* Unix 下通过 stty 关闭回显
      $sttyMode = shell_exec("stty -g 2>/dev/null");
      if ($sttyMode) {
        shell_exec("stty -echo");
      }
    }
    $input = trim(fgets(STDIN));
    if ($sttyMode ?? false) {
      shell_exec("stty " . escapeshellarg($sttyMode));
    }
    $this->line("");
    return $input;
  }
  /**
   * 覆写 App::run：执行命令分发并以退出码结束
   *
   * @return void
   */
  public function run()
  {
    exit($this->handle());
  }
}
