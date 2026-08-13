<?php

namespace kernel\Foundation\Console;

use kernel\Foundation\App;
use kernel\Foundation\Config;

/**
 * 控制台应用
 *
 * 继承 App 但跳过 HTTP 初始化（路由加载、Request 创建），提供 CLI 命令注册、
 * 参数解析、彩色输出与交互输入能力。
 *
 * 命令处理器支持两种形式：
 * 1. 闭包：function (Console $console, array $args, array $options): int
 * 2. 命令类：类实现 handle(Console $console, array $args, array $options): int 方法，
 *    注册时传入类名（如 "App\\Commands\\HelloCommand"），由本类实例化并调用
 *
 * 命令名支持冒号命名空间（如 make:controller）；输入空参数、help、-h 或 --help
 * 时自动列出全部已注册命令。CLI 环境下异常/错误输出到 stderr 并以非 0 退出。
 *
 * 入口脚本：kernel/console
 *   $console = new Console("kernel");
 *   $console->register("hello", function ($console, $args, $options) {
 *     $console->info("Hello " . ($args[0] ?? "World"));
 *     return 0;
 *   });
 *   $console->run(); // 内部调用 handle() 分发命令并以退出码结束
 *
 * 命令行调用：php kernel/console hello Tianjian --name=john
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
    parent::__construct($AppId, $KernelId, false);

    $this->argv = isset($GLOBALS['argv']) ? array_slice($GLOBALS['argv'], 1) : [];

    //* 让 getApp() 在 CLI 环境下可用
    $GLOBALS['App'] = $this;

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

    //* 自动发现内核与当前应用的命令类（目录存在时才扫描）
    $this->discoverBuiltin();
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
   * 自动发现内核与当前应用的命令类
   *
   * 实例化 Console 时自动调用，扫描两个约定目录（存在才扫描）：
   * - 内核：{F_KERNEL_ROOT}/Commands，命名空间 {F_KERNEL_ID}\Commands
   * - 当前应用：{F_APP_ROOT}/Commands，命名空间 {F_APP_ID}\Commands
   *
   * 内核与应用目录相同（例如 AppId 为 kernel 时）只扫描一次，避免重复注册。
   * 先注册内核命令，再注册应用命令，应用命令同名时覆盖内核命令。
   *
   * @return void
   */
  protected function discoverBuiltin(): void
  {
    //* 内核命令
    if (F_KERNEL_ROOT !== F_APP_ROOT) {
      $this->discover(F_KERNEL_ROOT . "/Commands", F_KERNEL_ID . "\\Commands");
    }
    //* 当前应用命令
    $this->discover(F_APP_ROOT . "/Commands", F_APP_ID . "\\Commands");
  }
  /**
   * 获取已注册的全部命令
   *
   * @return array
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
   * @param array|null $argv 命令行参数（不含脚本名）。不传时使用构造时捕获的 GLOBALS['argv']
   * @return integer 退出码
   */
  public function handle(?array $argv = null): int
  {
    if ($argv === null) {
      $argv = $this->argv;
    }

    //* 解析选项与位置参数
    [$name, $arguments, $options] = $this->parseArguments($argv);

    $this->commandName = $name;
    $this->arguments = $arguments;
    $this->options = $options;

    //* 帮助
    if ($name === "" || $name === "help" || $name === "-h" || $name === "--help") {
      return $this->listCommands();
    }

    if (!isset($this->commands[$name])) {
      $this->error("Command \"{$name}\" is not defined.");
      $this->line("");
      return $this->listCommands(1);
    }

    $command = $this->commands[$name];
    return $this->execute($command);
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

    if (empty($this->commands)) {
      $this->warning("No commands registered.");
      return 0;
    }

    $this->line("Available commands:", "33");
    $names = array_keys($this->commands);
    $maxLength = max(array_map("strlen", $names));
    ksort($this->commands);
    foreach ($this->commands as $name => $command) {
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
