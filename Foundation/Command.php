<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Response;

class Command
{
  private $process = null;
  private $pipes = [];
  private $env = [];
  private $options = [];
  private string $cwd = "/";
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
        "pipe", "r" // 标准输入，子进程从此管道中读取数
      ],
      [
        "pipe", "w" // 标准输出，子进程向此管道中写入数
      ],
      [
        "file", F_APP_ROOT . "/Data/proc_outpus.txt", "a" // 标准错误，写入到一个文件
      ]
    ];
    $this->process = $process = proc_open($this->initCommand, $descriptorspec, $pipes, $this->cwd, $this->env, $this->options);
    $this->pipes = &$pipes;
    $this->status = proc_get_status($process);
    Response::intercept(function () use ($process) {
      if (is_resource($process)) {
        proc_close($process);
      }
    });
  }
  public function exec(string $command, array $env = [], array $options = []): string
  {
    $oldEnv = $this->env;
    $oldOptions = $this->options;
    $this->env = Arr::merge($this->env, $env);
    $this->options = Arr::merge($this->options, $options);
    $this->init();

    $command = escapeshellcmd($command);
    $execResult = fwrite($this->pipes[0], "$command 2>&1;");
    fclose($this->pipes[0]);
    if ($execResult !== 0) {
      $result = false;
      fclose($this->pipes[1]);
    } else {
      $result = stream_get_contents($this->pipes[1]);
      fclose($this->pipes[1]);
    }

    $this->env = $oldEnv;
    $this->options = $oldOptions;
    $this->status = proc_get_status($this->process);

    return $result;
  }
  public function cd(string $cwd = "/"): Command
  {
    $this->cwd = $cwd;
    return $this;
  }
  public function echo(string $content): string
  {
    return $this->exec("echo " . escapeshellarg($content));
  }
  public function which(string $programName): string
  {
    return $this->exec("which " . escapeshellarg($programName));
  }
  public function pwd(): string
  {
    return $this->exec("pwd");
  }
  public function exitcode(): int
  {
    if (!is_resource($this->process)) {
      return null;
    }
    if ($this->status['running']) return -1;
    return $this->status['exitcode'];
  }
  public function ln(string $source, string $target, string $options = "-s",)
  {
    return $this->exec("sudo ln $options $source $target");
  }
  public function whereis(string $target)
  {
    return $this->exec("whereis $target");
  }
}
