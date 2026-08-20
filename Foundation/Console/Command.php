<?php

namespace kernel\Foundation\Console;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Exception;

/**
 * 系统命令执行器
 *
 * 基于 proc_open 封装，支持三种执行模式：
 * 1. 单次进程：每次 exec() 打开一个新进程，执行后关闭（默认）
 * 2. 会话复用：open() 后复用长驻 shell 进程，通过随机标记分隔每次命令的输出
 * 3. 异步执行：start() 非阻塞启动，poll()/wait() 轮询获取结果
 *
 * 同步执行支持 onStdout()/onStderr() 实时输出回调、超时与最大输出限制。
 *
 * 线程安全：本类非线程安全，同一实例不应被多个并发执行上下文共享。
 * 平台差异：会话模式（open()）仅支持 Unix/Linux，Windows 下返回 false。
 *
 * @link https://www.php.net/manual/en/function.proc-open.php
 */
class Command
{
  /** @var resource|null 子进程句柄（proc_open 返回值），null 表示当前无进程 */
  private $process = null;
  /** @var array<int, resource|null> 子进程管道：0=stdin, 1=stdout, 2=stderr */
  private $pipes = [];
  /** @var array 环境变量，作为 proc_open 的 env 参数，exec() 时与传入值合并 */
  private $env = [];
  /** @var array proc_open 的 options 参数（bypass_shell、suppress_errors 等） */
  private $options = [];
  /** @var string 当前工作目录，默认应用根目录 Path::root() */
  private string $cwd = "";
  /** @var string 初始化命令（shell 路径），exec() 执行的命令基于它运行 */
  private $initCommand = "";
  /** @var int|null 最近一次执行的退出码，执行中/尚未执行时为 null */
  private ?int $lastExitcode = null;
  /** @var bool 最近一次执行是否超时 */
  private $timedOut = false;
  /** @var bool 最近一次执行是否因输出超限被终止 */
  private $outputExceeded = false;
  /** @var int 命令执行超时（秒），0 表示不限制，默认 60 */
  private $timeout = 60;
  /** @var int 最大输出字节数（stdout+stderr），0 表示不限制，默认 10MB */
  private $maxOutput = 10485760;
  /** @var array proc_get_status 返回的进程状态结构 */
  private $status = [
    "command" => "",
    "pid" => 0,
    "running" => false,
    "signaled" => false,
    "stopped" => false,
    "exitcode" => -1,
    "termsig" => 0,
    "stopsig" => 0
  ];

  /* ==================== 会话状态 ==================== */
  /** @var bool 会话模式是否已开启 */
  private $sessionOpened = false;
  /** @var string 会话命令结束标记，用于从共享 stdout 中切分单次命令输出并解析退出码 */
  private $sessionMarker = "";

  /* ==================== 异步状态 ==================== */
  /** @var string 最近一次执行的命令原文（同步/异步/会话通用） */
  private $asyncCommand = "";
  /** @var string 异步执行累计的 stdout 内容 */
  private $asyncStdout = "";
  /** @var string 异步执行累计的 stderr 内容 */
  private $asyncStderr = "";
  /** @var int 本次执行累计读取的输出字节数（stdout + stderr） */
  private $totalBytes = 0;

  /* ==================== 输出回调 ==================== */
  /** @var callable|null stdout 实时输出回调，参数为输出片段 */
  private $stdoutCallback = null;
  /** @var callable|null stderr 实时输出回调，参数为输出片段 */
  private $stderrCallback = null;

  /**
   * 便捷静态执行：单次执行命令并返回标准输出
   *
   * @param string $command 命令
   * @param array $env 环境变量
   * @param array $options proc_open 选项
   * @return string 标准输出
   */
  public static function run(string $command, array $env = [], array $options = []): string
  {
    return (new static())->exec($command, $env, $options);
  }
  /**
   * 构建命令执行器
   *
   * @link https://www.php.net/manual/en/function.proc-open.php
   * @param array $env 环境变量，exec() 执行时与传入的 env 合并
   * @param array $options proc_open 选项（bypass_shell、suppress_errors、cwd 等）
   * @param string $command 初始化命令（shell 路径），exec() 执行的命令基于该命令运行。
   *                        例如传值 /bin/bash，则后续 exec() 传入的是 bash 可执行的命令
   */
  function __construct(array $env = [], array $options = [], string $command = "/bin/bash")
  {
    $this->initCommand = $command;
    $this->env = $env;
    $this->options = $options;
  }
  /**
   * 设置命令执行超时时间
   *
   * @param integer $seconds 超时秒数，0 表示不限制
   * @return Command
   */
  public function setTimeout(int $seconds): Command
  {
    $this->timeout = $seconds;
    return $this;
  }
  /**
   * 设置最大输出字节数，超过则终止命令
   *
   * @param integer $bytes 最大字节数，0 表示不限制
   * @return Command
   */
  public function setMaxOutput(int $bytes): Command
  {
    $this->maxOutput = $bytes;
    return $this;
  }
  /**
   * 注册标准输出实时回调
   *
   * @param callable $callback 回调函数，参数为输出片段
   * @return Command
   */
  public function onStdout(callable $callback): Command
  {
    $this->stdoutCallback = $callback;
    return $this;
  }
  /**
   * 注册标准错误实时回调
   *
   * @param callable $callback 回调函数，参数为输出片段
   * @return Command
   */
  public function onStderr(callable $callback): Command
  {
    $this->stderrCallback = $callback;
    return $this;
  }

  /* ==================== 会话复用 ==================== */
  /**
   * 开启长驻 shell 会话，之后 exec()/execResult() 将复用该会话进程
   * Windows 下不支持会话模式，返回 false
   *
   * @return bool 是否成功开启
   */
  public function open(): bool
  {
    if ($this->isOpen()) {
      return true;
    }
    if (strpos(PHP_OS, "WIN") !== false) {
      return false;
    }
    $this->initProcess();
    if (!is_resource($this->process)) {
      return false;
    }
    $this->sessionOpened = true;
    $this->sessionMarker = "F_KERNEL_CMD_END_" . bin2hex(random_bytes(8));
    return true;
  }
  /**
   * 关闭长驻 shell 会话
   *
   * @return void
   */
  public function close(): void
  {
    if (!$this->sessionOpened && !is_resource($this->process)) {
      return;
    }
    $this->sessionOpened = false;
    $this->closePipes();
    if (is_resource($this->process)) {
      proc_terminate($this->process, 15);
      proc_close($this->process);
    }
    $this->process = null;
  }
  /**
   * 会话是否开启且进程存活
   *
   * @return bool
   */
  public function isOpen(): bool
  {
    return $this->sessionOpened && is_resource($this->process);
  }

  /* ==================== 异步执行 ==================== */
  /**
   * 非阻塞启动命令执行
   * 若已存在运行中的进程或会话，会先关闭
   *
   * @param string $command 命令
   * @param array $env 环境变量
   * @param array $options 选项
   * @return Command
   */
  public function start(string $command, array $env = [], array $options = []): Command
  {
    if ($this->sessionOpened) {
      $this->close();
    }
    if ($this->isRunning()) {
      $this->terminate();
    }
    $this->env = Arr::merge($this->env, $env);
    $this->options = Arr::merge($this->options, $options);
    $this->prepare($command);
    return $this;
  }
  /**
   * 异步命令是否仍在运行
   *
   * @return bool
   */
  public function isRunning(): bool
  {
    if ($this->sessionOpened || !is_resource($this->process)) {
      return false;
    }
    $this->status = proc_get_status($this->process);
    return $this->status['running'];
  }
  /**
   * 非阻塞轮询，读取当前已产生的输出
   *
   * @return array ["running" => bool, "exitcode" => int|null, "stdout" => string, "stderr" => string]
   */
  public function poll(): array
  {
    if (!$this->isRunning()) {
      return $this->collectResult();
    }
    $this->readAvailable();
    return [
      "running" => true,
      "exitcode" => null,
      "stdout" => $this->asyncStdout,
      "stderr" => $this->asyncStderr
    ];
  }
  /**
   * 阻塞等待异步命令执行完成
   *
   * @param integer $timeout 等待超时秒数，0 表示按 setTimeout() 配置
   * @return array ["exitcode" => int|null, "stdout" => string, "stderr" => string, "timedout" => bool, "output_exceeded" => bool, "command" => string]
   */
  public function wait(int $timeout = 0): array
  {
    if (!$this->isRunning()) {
      return $this->collectResult();
    }
    $start = microtime(true);
    while ($this->isRunning()) {
      $this->readAvailable();
      $elapsed = microtime(true) - $start;
      if ($this->timeout > 0 && $elapsed > $this->timeout) {
        $this->timedOut = true;
        $this->terminate();
        break;
      }
      if ($timeout > 0 && $elapsed > $timeout) {
        $this->timedOut = true;
        $this->terminate();
        break;
      }
      usleep(20000);
    }
    return $this->collectResult();
  }
  /**
   * 向子进程标准输入写入数据（用于交互式命令）
   *
   * @param string $data 数据
   * @return Command
   */
  public function input(string $data): Command
  {
    $this->writeStream(0, $data);
    return $this;
  }

  /* ==================== 同步执行 ==================== */
  /**
   * 执行命令并返回完整执行结果
   *
   * @param string $command 命令
   * @param array $env 环境变量，与构造时的环境变量合并
   * @param array $options 选项，与构造时的选项合并
   * @return array ["exitcode" => int|null, "stdout" => string, "stderr" => string, "timedout" => bool, "output_exceeded" => bool, "command" => string]
   */
  public function execResult(string $command, array $env = [], array $options = []): array
  {
    $oldEnv = $this->env;
    $oldOptions = $this->options;
    $this->env = Arr::merge($this->env, $env);
    $this->options = Arr::merge($this->options, $options);

    if ($this->isRunning()) {
      $this->terminate();
    }

    if ($this->isOpen()) {
      $result = $this->execInSession($command);
    } else {
      $result = $this->execInProcess($command);
    }

    $this->env = $oldEnv;
    $this->options = $oldOptions;
    return $result;
  }
  /**
   * 执行命令
   *
   * @param string $command 命令
   * @param array $env 环境变量
   * @param array $options 选项
   * @return string 标准输出。如需完整结果（含退出码/错误输出）请使用 execResult()
   */
  public function exec(string $command, array $env = [], array $options = []): string
  {
    return $this->execResult($command, $env, $options)["stdout"];
  }

  /* ==================== 内部实现 ==================== */
  /**
   * 打开子进程（proc_open）并初始化管道
   *
   * - stdin/stdout/stderr 三个管道均设为非阻塞
   * - Windows 下环境变量为空时传 null，否则 proc_open 失败
   * - 注册 shutdown 回调，保证脚本结束（含异常/致命错误）时进程资源被回收
   *
   * @throws Exception proc_open 失败时抛出
   */
  private function initProcess(): void
  {
    $descriptorspec = [
      ['pipe', 'r'], // 标准输入
      ['pipe', 'w'], // 标准输出
      ['pipe', 'w']  // 标准错误
    ];

    // Windows 下必须传 null 环境变量，否则 proc_open 失败
    $env = $this->env;
    if (strpos(PHP_OS, "WIN") !== false && !$env) {
      $env = null;
    }

    $this->process = $process = @proc_open($this->initCommand, $descriptorspec, $pipes, $this->cwd ?: Path::root(), $env, $this->options);
    if ($process === false) {
      $this->process = null;
      throw new Exception("服务器错误", 500, "500:CommandError", "proc_open执行失败: {$this->initCommand}");
    }

    stream_set_blocking($pipes[0], FALSE);
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    $this->pipes = $pipes;
    $this->status = proc_get_status($process);
    register_shutdown_function(function () use ($process) {
      if (is_resource($process)) {
        proc_close($process);
      }
    });
  }
  /**
   * 准备一次执行：重置执行状态、打开进程、写入命令
   *
   * Windows 下将命令交给 `cmd /c` 执行（临时覆盖 initCommand），
   * Unix 下直接向 shell 写入命令。
   *
   * @param string $command 待执行的命令原文
   */
  private function prepare(string $command): void
  {
    $finalCommand = escapeshellcmd($command);
    if (strpos(PHP_OS, "WIN") !== false) {
      $this->initCommand = "cmd /c {$finalCommand}";
      $finalCommand = "";
    }
    $this->status["command"] = $command;
    $this->asyncCommand = $command;
    $this->timedOut = false;
    $this->outputExceeded = false;
    $this->totalBytes = 0;
    $this->lastExitcode = null;
    $this->asyncStdout = "";
    $this->asyncStderr = "";
    $this->initProcess();
    $this->writeStream(0, $finalCommand);
  }
  /**
   * 向指定管道写入数据（不关闭管道）
   *
   * 循环写入直到写完；当输入缓冲区满（fwrite 返回 0）时短暂等待后重试，
   * 超过 5 秒仍无法写入则放弃，避免永久阻塞。
   *
   * @param int $idx 管道索引：0=stdin, 1=stdout, 2=stderr
   * @param string $data 要写入的数据
   */
  private function writeStream(int $idx, string $data): void
  {
    $stream = $this->pipes[$idx] ?? null;
    if (!is_resource($stream)) {
      return;
    }
    if ($data === "") {
      return;
    }
    $length = strlen($data);
    $written = 0;
    $start = microtime(true);
    while ($written < $length) {
      $n = fwrite($stream, substr($data, $written));
      if ($n === false) {
        break;
      }
      if ($n === 0) {
        // 输入缓冲区已满，等待可写；超过 5 秒放弃
        if (microtime(true) - $start > 5) {
          break;
        }
        usleep(1000);
        continue;
      }
      $written += $n;
    }
  }
  /**
   * 关闭指定管道并置空
   *
   * @param int $idx 管道索引：0=stdin, 1=stdout, 2=stderr
   */
  private function closeStream(int $idx): void
  {
    if (is_resource($this->pipes[$idx] ?? null)) {
      fclose($this->pipes[$idx]);
      $this->pipes[$idx] = null;
    }
  }
  /**
   * 非阻塞读取一次两个输出管道，触发回调并累计输出（异步轮询用）
   *
   * 使用 0 超时的 stream_select 同时轮询 stdout/stderr，避免任一管道阻塞；
   * 累计输出超过 maxOutput 时终止进程并置 outputExceeded 标记。
   */
  private function readAvailable(): void
  {
    $read = [];
    foreach ([1, 2] as $idx) {
      if (is_resource($this->pipes[$idx]) && !feof($this->pipes[$idx])) {
        $read[$idx] = $this->pipes[$idx];
      }
    }
    if (!$read) {
      return;
    }
    $write = null;
    $except = null;
    $ready = @stream_select($read, $write, $except, 0, 0);
    if ($ready === false || $ready === 0) {
      return;
    }
    foreach ($read as $idx => $stream) {
      $chunk = fread($stream, 8192);
      if ($chunk === false || $chunk === "") {
        fclose($stream);
        $this->pipes[$idx] = null;
        continue;
      }
      $this->totalBytes += strlen($chunk);
      if ($idx === 1) {
        $this->asyncStdout .= $chunk;
        $this->emit($this->stdoutCallback, $chunk);
      } else {
        $this->asyncStderr .= $chunk;
        $this->emit($this->stderrCallback, $chunk);
      }
      if ($this->maxOutput > 0 && $this->totalBytes > $this->maxOutput) {
        $this->outputExceeded = true;
        $this->terminate();
      }
    }
  }
  /**
   * 阻塞读取输出直到进程结束（或会话命令标记出现）
   *
   * @param string|null $marker 会话命令结束标记，null 表示读取到进程结束
   * @return array [stdout, stderr]
   */
  private function readToEnd(?string $marker = null): array
  {
    $stdout = "";
    $stderr = "";
    $start = microtime(true);
    $markerRegex = $marker !== null ? '/\n' . preg_quote($marker, '/') . '=(\d+)/' : null;

    while (true) {
      // 超时检查
      if ($this->timeout > 0) {
        $remaining = $this->timeout - (microtime(true) - $start);
        if ($remaining <= 0) {
          $this->timedOut = true;
          $this->terminate();
          break;
        }
      } else {
        $remaining = 60;
      }

      // 收集仍在打开的管道
      $read = [];
      foreach ([1, 2] as $idx) {
        if (is_resource($this->pipes[$idx]) && !feof($this->pipes[$idx])) {
          $read[$idx] = $this->pipes[$idx];
        }
      }
      if (!$read) {
        break;
      }

      $write = null;
      $except = null;
      $sec = (int) $remaining;
      $usec = (int) (($remaining - $sec) * 1000000);
      $ready = @stream_select($read, $write, $except, $sec, $usec);

      if ($ready === false) {
        break;
      }
      if ($ready === 0) {
        // select 超时，命令执行超时
        $this->timedOut = true;
        $this->terminate();
        break;
      }

      foreach ($read as $idx => $stream) {
        $chunk = fread($stream, 8192);
        if ($chunk === false || $chunk === "") {
          fclose($stream);
          $this->pipes[$idx] = null;
          continue;
        }
        $this->totalBytes += strlen($chunk);
        if ($idx === 1) {
          $stdout .= $chunk;
          $this->emit($this->stdoutCallback, $chunk);
        } else {
          $stderr .= $chunk;
          $this->emit($this->stderrCallback, $chunk);
        }
        if ($this->maxOutput > 0 && $this->totalBytes > $this->maxOutput) {
          $this->outputExceeded = true;
          $this->terminate();
          break 2;
        }
      }

      // 会话标记检测
      if ($markerRegex !== null && preg_match($markerRegex, $stdout, $m)) {
        $pos = strrpos($stdout, "\n" . $marker . "=");
        if ($pos !== false) {
          $stdout = substr($stdout, 0, $pos);
        }
        $this->lastExitcode = (int) $m[1];
        break;
      }

      // 进程已结束则排空剩余输出后退出
      $status = proc_get_status($this->process);
      $this->status = $status;
      if (!$status['running']) {
        foreach ([1, 2] as $idx) {
          if (is_resource($this->pipes[$idx])) {
            while (($chunk = fread($this->pipes[$idx], 8192)) !== false && $chunk !== "") {
              if ($idx === 1) {
                $stdout .= $chunk;
                $this->emit($this->stdoutCallback, $chunk);
              } else {
                $stderr .= $chunk;
                $this->emit($this->stderrCallback, $chunk);
              }
            }
            fclose($this->pipes[$idx]);
            $this->pipes[$idx] = null;
          }
        }
        $this->lastExitcode = $status['exitcode'];
        break;
      }
    }

    return [$stdout, $stderr];
  }
  /**
   * 单次进程执行：准备 → 关闭 stdin → 读到结束 → 收尾
   *
   * 单次进程模式下 stdin 不复用，提前关闭以免子进程阻塞等待输入；
   * 进程结束后由 finalize() 关闭进程并回收管道资源。
   *
   * @param string $command 待执行的命令
   * @return array 完整执行结果（结构见 finalize()）
   */
  private function execInProcess(string $command): array
  {
    $this->prepare($command);
    $this->closeStream(0);
    [$stdout, $stderr] = $this->readToEnd();
    return $this->finalize([$stdout, $stderr]);
  }
  /**
   * 会话执行：复用长驻 shell，通过随机标记分隔输出并解析退出码
   *
   * 命令包装为：cd {cwd} && {command}; echo "\n{marker}=$?"
   * 随后在共享 stdout 中按标记切分出本次命令的输出，并从标记中解析退出码。
   *
   * @param string $command 待执行的命令
   * @return array 完整执行结果（结构见 finalize()）
   */
  private function execInSession(string $command): array
  {
    $marker = $this->sessionMarker;
    $this->status["command"] = $command;
    $this->asyncCommand = $command;
    $cmd = "cd " . escapeshellarg($this->cwd ?: Path::root()) . " && " . escapeshellcmd($command) . "; echo \"\n{$marker}=\$?\"";
    $this->writeStream(0, $cmd . "\n");
    [$stdout, $stderr] = $this->readToEnd($marker);
    return $this->finalize([$stdout, $stderr], true);
  }
  /**
   * 收尾：设置退出码、关闭进程/管道，返回结果数组
   *
   * @param bool $keepProcess true 时保留进程（会话模式）
   */
  private function finalize(array $outputs, bool $keepProcess = false): array
  {
    if (!$keepProcess) {
      $this->closeStream(0);
      if (is_resource($this->process)) {
        $this->status = proc_get_status($this->process);
        if (!$this->status['running'] && $this->lastExitcode === null) {
          $this->lastExitcode = $this->status['exitcode'];
        }
        proc_close($this->process);
      }
      $this->process = null;
      $this->closePipes();
    }

    return [
      "exitcode" => $this->lastExitcode,
      "stdout" => $outputs[0],
      "stderr" => $outputs[1],
      "timedout" => $this->timedOut,
      "output_exceeded" => $this->outputExceeded,
      "command" => $this->asyncCommand
    ];
  }
  /**
   * 汇总异步执行结果并清理资源
   *
   * 先排空剩余管道输出，再交由 finalize() 关闭进程并返回结果。
   *
   * @return array 完整执行结果（结构见 finalize()）
   */
  private function collectResult(): array
  {
    // 排空剩余输出
    foreach ([1, 2] as $idx) {
      if (is_resource($this->pipes[$idx])) {
        while (($chunk = fread($this->pipes[$idx], 8192)) !== false && $chunk !== "") {
          if ($idx === 1) {
            $this->asyncStdout .= $chunk;
          } else {
            $this->asyncStderr .= $chunk;
          }
        }
        fclose($this->pipes[$idx]);
        $this->pipes[$idx] = null;
      }
    }
    return $this->finalize([$this->asyncStdout, $this->asyncStderr]);
  }
  /**
   * 关闭所有管道
   */
  private function closePipes(): void
  {
    foreach ([0, 1, 2] as $idx) {
      $this->closeStream($idx);
    }
  }
  /**
   * 触发输出回调（存在且可调用时执行）
   *
   * @param callable|null $callback 输出回调
   * @param string $chunk 输出片段
   */
  private function emit($callback, string $chunk): void
  {
    if (is_callable($callback)) {
      call_user_func($callback, $chunk);
    }
  }

  /* ==================== 便捷命令 ==================== */
  /**
   * 切换目录
   *
   * @param string $cwd 目标目录
   * @return Command
   */
  public function cd(string $cwd = "/"): Command
  {
    $this->cwd = $cwd;
    return $this;
  }
  /**
   * 输出内容
   *
   * @param string $content echo的内容
   * @return string
   */
  public function echo(string $content): string
  {
    return $this->exec("echo " . escapeshellarg($content));
  }
  /**
   * 用于查找文件
   * which指令会在环境变量$PATH设置的目录里查找符合条件的文件。
   *
   * @param string $fileName 文件名称，例如 php 最终执行的命令就是 which php
   * @return string
   */
  public function which(string $fileName): string
  {
    return $this->exec("which " . escapeshellarg($fileName));
  }
  /**
   * 获取当前工作目录
   * 执行 pwd 指令可立刻得知您目前所在的工作目录的绝对路径名称。
   *
   * @return string
   */
  public function pwd(): string
  {
    return $this->exec("pwd");
  }
  /**
   * 命令执行退出码
   * 返回最近一次执行（同步/异步/会话）的退出码；执行中或尚未执行返回 null
   *
   * @return integer|null
   */
  public function exitcode(): ?int
  {
    return $this->lastExitcode;
  }
  /**
   * 最近一次执行是否成功（退出码为 0）
   *
   * @return bool
   */
  public function isSuccessful(): bool
  {
    return $this->lastExitcode === 0;
  }
  /**
   * 最近一次执行是否超时
   *
   * @return bool
   */
  public function isTimedOut(): bool
  {
    return $this->timedOut;
  }
  /**
   * 最近一次执行是否因输出超限被终止
   *
   * @return bool
   */
  public function isOutputExceeded(): bool
  {
    return $this->outputExceeded;
  }
  /**
   * 终止当前子进程
   * 先发 SIGTERM 优雅终止，2 秒内未退出则升级为 SIGKILL
   *
   * @param integer $signal 首次发送的信号，默认 SIGTERM(15)
   * @param integer $escalateAfter 等待秒数后升级 SIGKILL，0 表示不升级
   * @return void
   */
  public function terminate(int $signal = 15, int $escalateAfter = 2): void
  {
    if (!is_resource($this->process)) {
      return;
    }
    proc_terminate($this->process, $signal);
    if ($escalateAfter <= 0) {
      return;
    }
    $start = microtime(true);
    while (microtime(true) - $start < $escalateAfter) {
      $status = proc_get_status($this->process);
      if (!$status['running']) {
        return;
      }
      usleep(100000);
    }
    if (proc_get_status($this->process)['running']) {
      proc_terminate($this->process, 9);
    }
  }
  /**
   * 软连接
   * 为某一个文件在另外一个位置建立一个同步的链接
   * 例如：ln -s /bin/php /usr/bin/php
   *
   * @param string $source 文件源路径
   * @param string $target 软连接到的目标路径
   * @param string $options 选项，默认是 -s 符号链接（symbolic）的意思
   * @return string
   */
  public function ln(string $source, string $target, string $options = "-s"): string
  {
    return $this->exec("ln {$options} " . escapeshellarg($source) . " " . escapeshellarg($target));
  }
  /**
   * 用于查找文件
   * 该指令会在特定目录中查找符合条件的文件。这些文件应属于原始代码、二进制文件，或是帮助文件。
   * 该指令只能用于查找二进制文件、源代码文件和man手册页，一般文件的定位需使用locate命令。
   *
   * @param string $target 文件名称
   * @return string
   */
  public function whereis(string $target): string
  {
    return $this->exec("whereis " . escapeshellarg($target));
  }
}
