<?php

namespace kernel\Foundation\Crontab;

use kernel\Foundation\FileSystem\Path;

/**
 * 定时任务基类
 *
 * 业务模块可继承本类并实现 plan()（计划）与 handle()（任务逻辑），
 * 由 kernel/Foundation/Crontab/Crons 管理器（命令 schedule:run 统一驱动）：
 * 扫描当前应用的 Crons/ 目录，对到期的任务调用 run()。
 *
 * plan() 返回一个计划描述，可用基类提供的助手方法构造：
 *   $this->everyMinute()           每分钟
 *   $this->everyFiveMinutes()      每 5 分钟
 *   $this->hourly()                每小时整点
 *   $this->daily($hour = 0)        每天指定小时（默认 0 点）
 *   $this->cron("0 3 * * *")       标准 5 段 cron 表达式
 *
 * 上次执行时间持久化在 Data 目录的 cron/{类名}.last，保证进程重启后仍按周期去重。
 */
abstract class Cron
{
  /**
   * 定义执行计划，子类必须覆盖
   *
   * @return array{type:string,...}
   */
  protected function plan(): array
  {
    // 默认每天 0 点；子类应覆盖为自身计划
    return $this->daily(0);
  }

  /**
   * 任务逻辑，子类实现
   *
   * @return void
   */
  public function handle(): void
  {
  }

  /**
   * 运行入口：先执行 handle()，再记录执行时间
   *
   * @return void
   */
  public function run(): void
  {
    $this->handle();
    $this->markRun();
  }

  /**
   * 判断当前是否到期（相对上次执行时间）
   *
   * @return boolean
   */
  public function due(): bool
  {
    $plan = $this->plan();
    $tick = $this->currentTick($plan, time());
    if ($tick === null) {
      return false;
    }
    $last = $this->lastRun();
    return $last === null || $last < $tick;
  }

  /**
   * 计算 plan 在 <= $now 的最近一次应触发时刻
   *
   * @param array $plan
   * @param integer $now
   * @return integer|null
   */
  protected function currentTick(array $plan, int $now): ?int
  {
    switch ($plan["type"] ?? "") {
      case "interval":
        $seconds = (int)($plan["seconds"] ?? 60);
        if ($seconds <= 0) {
          return null;
        }
        return intdiv($now, $seconds) * $seconds;

      case "daily":
        $hour = (int)($plan["hour"] ?? 0);
        $minute = (int)($plan["minute"] ?? 0);
        $today = mktime($hour, $minute, 0, (int)date("n", $now), (int)date("j", $now), (int)date("Y", $now));
        if ($now >= $today) {
          return $today;
        }
        return mktime($hour, $minute, 0, (int)date("n", $now), (int)date("j", $now) - 1, (int)date("Y", $now));

      case "cron":
        return $this->prevCronTick((string)($plan["expr"] ?? ""), $now);

      default:
        return null;
    }
  }

  /**
   * 从 $now 向前回溯，找到满足 cron 表达式的最近时刻
   *
   * @param string $expr 5 段表达式：分 时 日 月 周
   * @param integer $now
   * @return integer|null
   */
  protected function prevCronTick(string $expr, int $now): ?int
  {
    $fields = preg_split("/\s+/", trim($expr));
    if (count($fields) !== 5) {
      return null;
    }
    [$mF, $hF, $dF, $moF, $dwF] = $fields;

    $mins = $this->cronFieldValues($mF, 0, 59);
    $hours = $this->cronFieldValues($hF, 0, 23);
    $doms = $this->cronFieldValues($dF, 1, 31);
    $mons = $this->cronFieldValues($moF, 1, 12);
    $dows = $this->cronFieldValues($dwF, 0, 7);

    $domStar = $dF === "*";
    $dowStar = $dwF === "*";

    $limit = $now - 5 * 366 * 86400;
    for ($t = $now; $t > $limit; $t -= 60) {
      $min = (int)date("i", $t);
      $hour = (int)date("G", $t);
      $dom = (int)date("j", $t);
      $mon = (int)date("n", $t);
      $dow = (int)date("w", $t); // 0=周日 .. 6=周六

      $domOk = in_array($dom, $doms, true);
      $dowOk = in_array($dow, $dows, true);
      // Vixie cron 语义：dom 与 dow 同时受限时取「或」，否则取「且」
      $dayOk = ($domStar || $dowStar) ? ($domOk && $dowOk) : ($domOk || $dowOk);

      if (in_array($min, $mins, true) && in_array($hour, $hours, true)
        && in_array($mon, $mons, true) && $dayOk) {
        return $t;
      }
    }
    return null;
  }

  /**
   * 解析 cron 单段为允许值集合（支持通配、步长、区间、列表及其组合）
   *
   * @param string $field
   * @param integer $min
   * @param integer $max
   * @return array<int>
   */
  protected function cronFieldValues(string $field, int $min, int $max): array
  {
    if ($field === "*") {
      return range($min, $max);
    }
    $values = [];
    foreach (explode(",", $field) as $part) {
      if (strpos($part, "/") !== false) {
        [$range, $step] = explode("/", $part, 2);
        $step = (int)$step;
        if ($range === "*") {
          $lo = $min;
          $hi = $max;
        } elseif (strpos($range, "-") !== false) {
          [$lo, $hi] = explode("-", $range, 2);
          $lo = (int)$lo;
          $hi = (int)$hi;
        } else {
          $lo = $min;
          $hi = $max;
        }
        for ($v = $lo; $v <= $hi; $v += max(1, $step)) {
          $values[] = $v;
        }
      } elseif (strpos($part, "-") !== false) {
        [$lo, $hi] = explode("-", $part, 2);
        foreach (range((int)$lo, (int)$hi) as $v) {
          $values[] = $v;
        }
      } else {
        $values[] = (int)$part;
      }
    }
    return array_values(array_unique($values));
  }

  /**
   * 计划助手：每分钟
   *
   * @return array{type:string,seconds:int}
   */
  protected function everyMinute()
  {
    return ["type" => "interval", "seconds" => 60];
  }

  /**
   * 计划助手：每 5 分钟
   *
   * @return array{type:string,seconds:int}
   */
  protected function everyFiveMinutes()
  {
    return ["type" => "interval", "seconds" => 300];
  }

  /**
   * 计划助手：每小时整点
   *
   * @return array{type:string,seconds:int}
   */
  protected function hourly()
  {
    return ["type" => "interval", "seconds" => 3600];
  }

  /**
   * 计划助手：每天指定小时
   *
   * @param integer $hour
   * @param integer $minute
   * @return array{type:string,hour:int,minute:int}
   */
  protected function daily(int $hour = 0, int $minute = 0)
  {
    return ["type" => "daily", "hour" => $hour, "minute" => $minute];
  }

  /**
   * 计划助手：标准 cron 表达式
   *
   * @param string $expression
   * @return array{type:string,expr:string}
   */
  protected function cron(string $expression)
  {
    return ["type" => "cron", "expr" => $expression];
  }

  /**
   * 读取上次执行时间
   *
   * @return integer|null
   */
  protected function lastRun(): ?int
  {
    $file = $this->lastRunFile();
    if ($file === null || !is_file($file)) {
      return null;
    }
    $val = (int)file_get_contents($file);
    return $val > 0 ? $val : null;
  }

  /**
   * 记录本次执行时间
   *
   * @return void
   */
  protected function markRun(): void
  {
    $file = $this->lastRunFile();
    if ($file === null) {
      return;
    }
    $dir = dirname($file);
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, (string)time());
  }

  /**
   * 上次执行时间持久化文件路径
   *
   * 放在 Data 目录（应用状态），而非 Storage（上传/资源）。
   *
   * @return string|null
   */
  protected function lastRunFile(): ?string
  {
    $data = Path::data();
    if ($data === null) {
      return null;
    }
    return rtrim($data, "/\\") . "/cron/" . sha1(static::class) . ".last";
  }
}
