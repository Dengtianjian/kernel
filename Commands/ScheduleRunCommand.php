<?php

namespace kernel\Commands;

use kernel\Facades\Crons;

/**
 * 定时任务执行命令
 *
 * 命令名 schedule:run 在 kernel/console 中注册（Console::register）。
 *
 * 用法：
 *   php isdtj/console schedule:run
 *
 * 直接经 Crons 门面执行：业务应用可先经门面登记定时器（如 Crons::registerClass(...)）；
 * 未登记时门面自动实例化并扫描当前应用 Crons/ 目录，
 * 对继承 kernel\Foundation\Crontab\Cron 且到期的任务调用 run()。
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
    // 经门面执行：业务已登记则复用同一单例，否则自动扫描 App Crons/ 目录
    $ran = Crons::runDue();

    foreach (Crons::notFound() as $className) {
      $console->warning("Cron class not found: {$className}");
    }

    if ($ran === 0) {
      $console->info("No tasks due.");
    } else {
      $console->success("Done. {$ran} task(s) executed.");
    }
    return 0;
  }
}
