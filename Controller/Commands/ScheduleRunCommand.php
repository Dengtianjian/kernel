<?php

namespace kernel\Controller\Commands;
use kernel\Foundation\FileSystem\Path;

/**
 * 定时任务执行命令
 *
 * 命令名 schedule:run 在 kernel/Routes/index.php 中注册（Router::command）。
 *
 * 用法：
 *   php kernel/console schedule:run
 *
 * 扫描当前应用 Crons/ 目录下的定时任务类（继承 kernel\Foundation\Cron），
 * 按 plan() 定义的计划执行到期的任务。
 */
class ScheduleRunCommand
{
  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数（无）
   * @param array $options 选项参数（无）
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $cronsDirectory = Path::root() . "/Crons";
    if (!is_dir($cronsDirectory)) {
      $console->warning("No Crons/ directory found in " . Path::root());
      return 0;
    }

    $cronFiles = glob($cronsDirectory . "/*Cron.php");
    if (empty($cronFiles)) {
      $console->info("No cron classes found in {$cronsDirectory}");
      return 0;
    }

    $app = \getApp();
    if (!$app) {
      $console->error("App instance not found. Run schedule:run through the console entry.");
      return 1;
    }

    $namespace = $app->id() . "\\Crons";
    $ran = 0;
    foreach ($cronFiles as $file) {
      $className = $namespace . "\\" . pathinfo($file, PATHINFO_FILENAME);
      if (!class_exists($className)) {
        $console->warning("Cron class not found: {$className}");
        continue;
      }

      /** @var \kernel\Foundation\Cron $cron */
      $cron = new $className();
      if ($cron->due()) {
        $console->line("Running: {$className}");
        $cron->handle();
        $ran++;
      }
    }

    if ($ran === 0) {
      $console->info("No tasks due.");
    } else {
      $console->success("Done. {$ran} task(s) executed.");
    }
    return 0;
  }
}
