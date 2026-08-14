<?php

namespace kernel\Commands;

use kernel\Foundation\File\FileHelper;

/**
 * 运行定时任务命令
 *
 * 定时任务调度入口：每次执行时扫描 {F_APP_ROOT}/Crons/ 目录下的任务类，
 * 读取类属性 $schedule 声明的执行时间，匹配当前时刻的任务会被按需执行。
 *
 * 任务类约定（Crons/ 目录下每个 PHP 文件定义一个类）：
 *   namespace App\Crons;
 *   class CleanupTask
 *   {
 *     protected $schedule = "h2"; // 每天 2 点执行，null/缺省为每分钟
 *
 *     public function handle($console)
 *     {
 *         // 任务逻辑
 *     }
 *   }
 *
 * 用法：
 *   php {应用}/console schedule:run
 *
 * crontab 配置（每分钟调度一次，实际是否执行由 $schedule 决定）：
 *   * * * * * php /path/to/app/console schedule:run >> /var/log/cron.log 2>&1
 */
class ScheduleRunCommand
{
  /**
   * 命令名称
   *
   * @var string
   */
  protected $name = "schedule:run";
  /**
   * 命令说明
   *
   * @var string
   */
  protected $description = "Run scheduled tasks (Crons/)";

  /**
   * 执行命令
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数
   * @param array $options 选项参数
   * @return integer 退出码（有任务失败时返回 1）
   */
  public function handle($console, $args, $options): int
  {
    //* 扫描 Crons/ 目录下的任务类
    $tasks = $this->discoverTasks();
    if (empty($tasks)) {
      $console->warning("No task classes found in " . FileHelper::combinedFilePath(F_APP_ROOT, "Crons") . ".");
      return 0;
    }

    //* 匹配当前时刻应执行的任务
    $due = [];
    foreach ($tasks as $className => $schedule) {
      if ($this->match($schedule)) {
        $due[$className] = $schedule;
      }
    }

    if (empty($due)) {
      $console->info("No tasks scheduled to run at this time.");
      return 0;
    }

    $success = 0;
    $failed = 0;
    foreach ($due as $className => $schedule) {
      $console->line("Running {$className} ...", "36");
      try {
        $instance = new $className();
        $instance->handle($console);
        $console->success("  done");
        $success++;
      } catch (\Throwable $e) {
        $console->error("  failed: " . $e->getMessage());
        $failed++;
      }
    }

    $console->line("");
    $console->success("Finished: {$success} succeeded, {$failed} failed.");

    return $failed > 0 ? 1 : 0;
  }

  /**
   * 扫描应用 Crons/ 目录下的任务类
   *
   * 目录下每个 PHP 文件对应一个任务类，类命名空间为 {F_APP_ID}\Crons，
   * 子目录对应追加子命名空间（如 Crons/System/ 下为 {F_APP_ID}\Crons\System\）。
   * 读取类默认属性 $schedule 作为执行时间表达式，null 或缺省表示每分钟执行。
   *
   * @return array [类名 => 时间表达式]
   */
  protected function discoverTasks()
  {
    $directory = FileHelper::combinedFilePath(F_APP_ROOT, "Crons");
    if (!is_dir($directory)) {
      return [];
    }

    $namespace = F_APP_ID . "\\Crons";
    $tasks = [];
    $files = FileHelper::recursionScanDir($directory);
    foreach ($files as $file) {
      if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== "php") {
        continue;
      }
      $className = $namespace . "\\" . str_replace(DIRECTORY_SEPARATOR, "\\", substr($file, 0, -4));
      include_once(FileHelper::combinedFilePath($directory, $file));
      if (!class_exists($className)) {
        continue;
      }
      $properties = (new \ReflectionClass($className))->getDefaultProperties();
      $tasks[$className] = $properties["schedule"] ?? null;
    }

    return $tasks;
  }

  /**
   * 判断时间表达式是否匹配当前时刻
   *
   * @param string|null $time 时间表达式，null 或空字符串表示每分钟执行
   * @return boolean 是否匹配
   */
  protected function match($time)
  {
    if ($time === null || $time === "") {
      return true;
    }
    $time = $this->normalize($time);
    return in_array($time, $this->currentMatchRules(), true);
  }

  /**
   * 将带字母前缀的时间表达式归一化为标准时间格式
   *
   * y/m/d/h/i 依次尝试，命中则转换为对应时间串，未命中保持原样：
   *   y2027  => "2027-1-1 0:0"
   *   m10    => "10-1 0:0"
   *   d13    => "13 0:0"
   *   h13    => "13:0"
   *   i38    => "38"
   *
   * @param string $time 时间表达式
   * @return string 归一化后的时间串
   */
  protected function normalize($time)
  {
    $time = (string)$time;
    if (preg_match("/y\d+/", $time)) {
      $time = (intval(str_replace("y", "", $time))) . "-1-1 0:0";
    }
    if (preg_match("/m\d+/", $time)) {
      $time = (intval(str_replace("m", "", $time))) . "-1 0:0";
    }
    if (preg_match("/d\d+/", $time)) {
      $time = (intval(str_replace("d", "", $time))) . " 0:0";
    }
    if (preg_match("/h\d+/", $time)) {
      $time = (intval(str_replace("h", "", $time))) . ":0";
    }
    if (preg_match("/i\d+/", $time)) {
      $time = str_replace("i", "", $time);
    }
    return $time;
  }

  /**
   * 当前时刻所有可能被匹配的时间串
   *
   * 与 normalize() 归一化后的表达式比对，命中即说明当前时刻应执行。
   *
   * @return array 时间串列表
   */
  protected function currentMatchRules()
  {
    $date = strtotime(date("Y-m-d H:i:s"));
    $Year = intval(date('Y', $date));
    $Month = intval(date("m", $date));
    $Day = intval(date("d", $date));
    $Hour = intval(date("H", $date));
    $Minute = intval(date("i", $date));

    $MatchRules = [
      "{$Minute}",

      "{$Year}-{$Month}-{$Day} {$Hour}:{$Minute}",
      "{$Month}-{$Day} {$Hour}:{$Minute}",
      "{$Day} {$Hour}:{$Minute}",
      "{$Hour}:{$Minute}",
    ];
    if ($Minute === 0) {
      array_push($MatchRules,  "{$Hour}:0");
      array_push($MatchRules,  "{$Day} {$Hour}:0");
      array_push($MatchRules,  "{$Month}-{$Day} {$Hour}:0");
      array_push($MatchRules,  "{$Year}-{$Month}-{$Day} {$Hour}:0");
      array_push($MatchRules, "{$Year}-{$Month}-{$Day} {$Hour}");
    }
    if ($Hour === 0 && $Minute === 0) {
      array_push($MatchRules, "{$Day} 0:0");
      array_push($MatchRules, "{$Year}-{$Month}-{$Day} 0:0");
      array_push($MatchRules, "{$Year}-{$Month}-{$Day}");
    }
    if ($Day === 1 && $Hour === 0 && $Minute === 0) {
      array_push($MatchRules,  "{$Month}-1 0:0");
      array_push($MatchRules, "{$Year}-{$Month}-1 0:0");
      array_push($MatchRules, "{$Year}-{$Month}");
    }
    if ($Month === 1 && $Day === 1 && $Hour === 0 && $Minute === 0) {
      array_push($MatchRules, "{$Year}-1-1 0:0");
      array_push($MatchRules, "{$Year}");
    }

    return $MatchRules;
  }
}
