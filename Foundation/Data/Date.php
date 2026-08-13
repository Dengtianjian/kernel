<?php

namespace kernel\Foundation\Data;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/**
 * 时间工具类
 *
 * 提供高精度时间戳获取（微秒/毫秒）、单位转换、时间差与耗时、
 * 字符串解析与格式化等静态工具方法。全部方法无需实例化即可调用。
 *
 * 方法分为以下几组：
 * - 时间戳获取： microseconds、milliseconds
 * - 单位转换：   secondsToMilliseconds、millisecondsToSeconds
 * - 时间差耗时： elapsed、diff、humanize
 * - 解析格式化： parse、format
 *
 * @package kernel\Foundation\Data
 */
class Date
{
  // ========================
  //  时间戳获取
  // ========================

  /**
   * 获取当前时间戳（秒，微秒精度）
   *
   * @return float 当前时间戳，例如 1786635816.234411
   *
   * @example
   * Date::microseconds()   // 1786635816.234411
   */
  public static function microseconds(): float
  {
    return microtime(true);
  }

  /**
   * 获取当前时间戳（毫秒，整数）
   *
   * @return int 当前时间戳，例如 1786635816234
   *
   * @example
   * Date::milliseconds()   // 1786635816234
   */
  public static function milliseconds(): int
  {
    return (int) round(microtime(true) * 1000);
  }

  // ========================
  //  单位转换
  // ========================

  /**
   * 秒级时间戳转毫秒级
   *
   * @param int|float $seconds 秒级时间戳
   * @return int 毫秒级时间戳
   *
   * @example
   * Date::secondsToMilliseconds(1786635816.234)  // 1786635816234
   * Date::secondsToMilliseconds(1786635816)      // 1786635816000
   */
  public static function secondsToMilliseconds($seconds): int
  {
    return (int) round($seconds * 1000);
  }

  /**
   * 毫秒级时间戳转秒级
   *
   * @param int|float $milliseconds 毫秒级时间戳
   * @return float 秒级时间戳（毫秒精度）
   *
   * @example
   * Date::millisecondsToSeconds(1786635816234)  // 1786635816.234
   */
  public static function millisecondsToSeconds($milliseconds): float
  {
    return $milliseconds / 1000;
  }

  // ========================
  //  时间差与耗时
  // ========================

  /**
   * 获取自某个时间点（毫秒）以来的耗时
   *
   * @param int $startMs 起点毫秒时间戳（通常由 Date::milliseconds() 记录）
   * @return int 距当前毫秒差
   *
   * @example
   * $start = Date::milliseconds();
   * // ... 执行耗时逻辑 ...
   * Date::elapsed($start)   // 42
   */
  public static function elapsed(int $startMs): int
  {
    return self::milliseconds() - $startMs;
  }

  /**
   * 计算两个毫秒时间戳之间的差值
   *
   * @param int $startMs 起点毫秒时间戳
   * @param int|null $endMs 终点毫秒时间戳，默认当前毫秒时间戳
   * @return int 毫秒差（负数表示 $endMs 早于 $startMs）
   *
   * @example
   * Date::diff(1786635816000, 1786635816123)  // 123
   */
  public static function diff(int $startMs, ?int $endMs = null): int
  {
    return ($endMs ?? self::milliseconds()) - $startMs;
  }

  /**
   * 将毫秒耗时格式化为人类可读字符串
   *
   * @param int|float $ms 毫秒数
   * @return string 可读耗时，如 "832ms"、"1.3s"、"2m 5s"、"1h 30m"、"2d 5h"
   *
   * @example
   * Date::humanize(832)     // "832ms"
   * Date::humanize(1300)    // "1.3s"
   * Date::humanize(125000)  // "2m 5s"
   * Date::humanize(5400000) // "1h 30m"
   */
  public static function humanize($ms): string
  {
    $ms = (int) $ms;
    if ($ms < 1000) {
      return $ms . "ms";
    }

    $seconds = (int) round($ms / 1000);
    if ($seconds < 60) {
      return round($ms / 1000, 1) . "s";
    }

    $minutes = (int) floor($seconds / 60);
    $restSeconds = $seconds % 60;
    if ($minutes < 60) {
      return $minutes . "m " . $restSeconds . "s";
    }

    $hours = (int) floor($minutes / 60);
    $restMinutes = $minutes % 60;
    if ($hours < 24) {
      return $hours . "h " . $restMinutes . "m";
    }

    $days = (int) floor($hours / 24);
    $restHours = $hours % 24;
    return $days . "d " . $restHours . "h";
  }

  // ========================
  //  解析与格式化
  // ========================

  /**
   * 将字符串时间解析为秒级时间戳
   *
   * @param string $str 可被 strtotime 解析的时间字符串，如 "2026-08-13 10:00:00"
   * @return int|null 秒级时间戳；解析失败返回 null
   *
   * @example
   * Date::parse("2026-08-13 10:00:00")  // 1786...
   * Date::parse("invalid")              // null
   */
  public static function parse($str): ?int
  {
    if ($str === null || $str === "") {
      return null;
    }
    $timestamp = strtotime($str);
    return $timestamp === false ? null : $timestamp;
  }

  /**
   * 格式化时间戳为时间字符串
   *
   * 自动识别秒级与毫秒级时间戳（毫秒级约 1e12，秒级约 1e9）。
   *
   * @param int|float|null $timestamp 时间戳（秒级或毫秒级），默认当前时间
   * @param string $format date() 格式化字符串
   * @return string 格式化后的时间字符串
   *
   * @example
   * Date::format(1786635816)                       // "2026-08-13 10:00:00"
   * Date::format(1786635816234, "Y/m/d H:i")       // "2026/08/13 10:00"
   * Date::format(null, "Y年m月d日")                 // "2026年08月13日"
   */
  public static function format($timestamp = null, $format = "Y-m-d H:i:s"): string
  {
    if ($timestamp === null) {
      $timestamp = time();
    } elseif ($timestamp >= 100000000000) {
      // 毫秒级时间戳（13 位）自动转秒级
      $timestamp = (int) ($timestamp / 1000);
    } else {
      $timestamp = (int) $timestamp;
    }
    return date($format, $timestamp);
  }
}
