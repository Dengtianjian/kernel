<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Response;

class Command
{
  private $process = null;
  private $pipes = [];
  private $env = [];
  private $options = [];
  private string $cwd = "/";
  private $initCommand = "";
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
  /**
   * 构建
   * @link https://www.php.net/manual/en/function.proc-open.php
   * @param array $env 环境变量
   * @param array $options 选项
   * @param string $command 初始化命令，exec执行的命令基于该命令。例如 传值是 /bin/bash 那后续exec的值就是 bin/bash能执行的命令
   */
  function __construct(array $env = [], array $options = [], string $command = "/bin/bash")
  {
    $this->initCommand = $command;
    $this->env = $env;
    $this->options = $options;
  }
  private function init(): void
  {
    // $pipes 现在看起来是这样的：
    // 0 => 可以向子进程标准输入写入的句柄
    // 1 => 可以从子进程标准输出读取的句柄
    // 错误输出将被追加到文件 /tmp/error-output.txt

    $descriptorspec = [
      [
        'pipe', 'r' // 标准输入，子进程从此管道中读取数
      ],
      [
        'pipe', /*F_APP_ROOT . '/Data/outputs',*/ 'w' // 标准输出，子进程向此管道中写入数
      ],
      [
        'pipe', /*F_APP_ROOT . '/Data/errors',*/ 'w' // 标准错误，写入到一个文件
      ]
    ];
    $this->process = $process = proc_open($this->initCommand, $descriptorspec, $pipes, $this->cwd, $this->env, $this->options);
    if ($process === false) {
      throw new Exception("服务器错误", 500, "500:CommandError", "proc_open执行失败");
    }
    $this->pipes = &$pipes;
    $this->status = proc_get_status($process);
    register_shutdown_function(function () use ($process) {
      if (is_resource($process)) {
        proc_close($process);
      }
    });
  }
  /**
   * 执行命令
   *
   * @param string $command 命令
   * @param array $env 环境变量
   * @param array $options 选项
   * @return string 标准输出
   */
  public function exec(string $command, array $env = [], array $options = []): string
  {
    $oldEnv = $this->env;
    $oldOptions = $this->options;
    $this->env = Arr::merge($this->env, $env);
    $this->options = Arr::merge($this->options, $options);
    $this->init();

    $command = escapeshellcmd($command);
    fwrite($this->pipes[0], "$command 2>&1;");
    fclose($this->pipes[0]);

    $output = fread($this->pipes[1], 99999);
    $output = fread($this->pipes[2], 99999);

    $this->env = $oldEnv;
    $this->options = $oldOptions;
    $this->status = proc_get_status($this->process);

    return $output;
  }
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
   *
   * @return integer
   */
  public function exitcode(): int
  {
    if (!is_resource($this->process)) {
      return null;
    }
    if ($this->status['running']) return -1;
    return $this->status['exitcode'];
  }
  /**
   * 软连接
   * 为某一个文件在另外一个位置建立一个同步的链接
   * 例如：ln -s /bin/php /usr/bin/php
   *
   * @param string $source 文件源路径
   * @param string $target 软连接到的目标路径
   * @param string $options 选项，默认是 -s 符号链接（symbolic）的意思
   * @return void
   */
  public function ln(string $source, string $target, string $options = "-s",)
  {
    return $this->exec("sudo ln $options $source $target");
  }
  /**
   * 用于查找文件
   * 该指令会在特定目录中查找符合条件的文件。这些文件应属于原始代码、二进制文件，或是帮助文件。
   * 该指令只能用于查找二进制文件、源代码文件和man手册页，一般文件的定位需使用locate命令。
   *
   * @param string $target 文件名称
   * @return void
   */
  public function whereis(string $target)
  {
    return $this->exec("whereis $target");
  }
}
