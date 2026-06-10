<?php

namespace kernel\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

/**
 * 日期时间工具类
 *
 * 提供时间戳转换、毫秒/微秒获取、数字补零等静态方法
 */
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
  /**
   * 数字左侧补零
   *
   * 例如 padZero(5) 返回 "05"，padZero(3, 4) 返回 "0003"
   *
   * @param int $num 需要补零的数字
   * @param int $length 目标长度，默认为 2
   * @return string 补零后的字符串
   */
  public static function padZero(int $num, int $length = 2): string
  {
    return str_pad((string)$num, $length, '0', STR_PAD_LEFT);
  }
}
