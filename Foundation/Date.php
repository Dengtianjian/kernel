<?php

namespace kernel\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class Date
{
  /**
   * 获取微秒
   *
   * @return int
   */
  public static function microseconds()
  {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }
  /**
   * 获取毫秒
   *
   * @return int
   */
  public static function milliseconds()
  {
    list($usec, $sec) = explode(" ", microtime());
    return ((int)substr($usec, 2, 3) + $sec * 1000);
  }
  /**
   * 将各种格式的时间转为秒级时间戳
   * 支持：秒级时间戳、毫秒级时间戳、日期字符串（如 2024-01-01、2024-01-01 12:30:00）
   *
   * @param int|string $time 时间参数
   * @return int 秒级 Unix 时间戳，转换失败返回 0
   */
  public static function toTimestamp($time)
  {
    if (is_null($time) || $time === '') return 0;

    if (is_numeric($time)) {
      $time = intval($time);
      // 毫秒级时间戳（>= 10000000000，即13位），除以1000转为秒级
      if ($time >= 10000000000) {
        return intval($time / 1000);
      }
      return $time;
    }

    $timestamp = strtotime($time);
    return $timestamp !== false ? $timestamp : 0;
  }
}
