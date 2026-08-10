<?php

namespace kernel\Foundation\Data;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/**
 * 数值工具类
 *
 * 提供数值转换、范围判断/钳制、四舍五入、格式化、百分比、字节可读化等
 * 一系列静态工具方法。全部方法无需实例化即可调用。
 *
 * 方法分为以下几组：
 * - 转换：       val
 * - 范围判断：   isBetween、clamp
 * - 精度：       roundTo
 * - 格式化：     format、percentage、fixedDecimals、padDecimals、bytesToHuman
 *
 * @package kernel\Foundation\Data
 */
class Numeric
{
  // ========================
  //  转换
  // ========================

  /**
   * 将目标变量转为数值（int 或 float）
   *
   * - null → 0
   * - 数值字符串 "123" → 123、"3.14" → 3.14
   * - 非数值的标量通过 intval/floatval 兜底转换
   *
   * @param mixed $Target 目标变量
   * @return int|float 转换后的数值，类型由内容决定
   *
   * @example
   * Numeric::val(null)     // 0
   * Numeric::val("123")    // 123（int）
   * Numeric::val("3.14")   // 3.14（float）
   * Numeric::val(5)        // 5（int，原样返回）
   * Numeric::val(2.5)      // 2.5（float，原样返回）
   */
  static function val($Target)
  {
    if (is_null($Target)) {
      return 0;
    }
    if (is_numeric($Target)) {
      return $Target + 0;
    }
    return strpos((string) $Target, '.') === false
      ? intval($Target)
      : floatval($Target);
  }

  // ========================
  //  范围判断
  // ========================

  /**
   * 判断值是否位于指定范围 [min, max]（闭区间）
   *
   * @param int|float $value 待判断的值
   * @param int|float $min   最小值
   * @param int|float $max   最大值
   * @return bool
   *
   * @example
   * Numeric::isBetween(5, 1, 10)   // true
   * Numeric::isBetween(10, 1, 10)  // true（闭区间，边界值通过）
   * Numeric::isBetween(0, 1, 10)   // false
   */
  static function isBetween($value, $min, $max): bool
  {
    return $value >= $min && $value <= $max;
  }

  /**
   * 将值钳制在 [min, max] 区间内
   *
   * @param int|float $value 原始值
   * @param int|float $min   最小值
   * @param int|float $max   最大值
   * @return int|float 钳制后的值
   *
   * @example
   * Numeric::clamp(5, 1, 10)   // 5（不变）
   * Numeric::clamp(0, 1, 10)   // 1（提升到下限）
   * Numeric::clamp(15, 1, 10)  // 10（压到上限）
   */
  static function clamp($value, $min, $max)
  {
    return max($min, min($max, $value));
  }

  // ========================
  //  精度
  // ========================

  /**
   * 四舍五入到指定小数位
   *
   * PHP 原生 round() 的便捷封装，统一参数命名风格。
   *
   * @param float $value    要舍入的值
   * @param int   $decimals 小数位数，默认 0
   * @return float
   *
   * @example
   * Numeric::roundTo(3.14159, 2)  // 3.14
   * Numeric::roundTo(3.14159)     // 3.0
   * Numeric::roundTo(3.5)         // 4.0
   */
  static function roundTo($value, $decimals = 0): float
  {
    return round((float) $value, $decimals);
  }

  // ========================
  //  格式化
  // ========================

  /**
   * 格式化数值（千分位分隔 + 指定小数位）
   *
   * @param float  $value        数值
   * @param int    $decimals     小数位数，默认 2
   * @param string $decPoint     小数点字符，默认 '.'
   * @param string $thousandsSep 千分位分隔符，默认 ','
   * @return string 格式化后的数字字符串
   *
   * @example
   * Numeric::format(1234567.89)         // "1,234,567.89"
   * Numeric::format(1234567, 0)         // "1,234,567"
   * Numeric::format(1234.5, 2, '.', '') // "1234.50"（无千分位）
   */
  static function format($value, $decimals = 2, $decPoint = '.', $thousandsSep = ','): string
  {
    return number_format((float) $value, $decimals, $decPoint, $thousandsSep);
  }

  /**
   * 计算百分比
   *
   * @param int|float $value    分子
   * @param int|float $total    分母（总额）
   * @param int       $decimals 小数位数，默认 2
   * @return float 百分比数值，例如 28.57（表示 28.57%）
   *
   * @example
   * Numeric::percentage(2, 7)    // 28.57
   * Numeric::percentage(1, 3, 0) // 33.0
   */
  static function percentage($value, $total, $decimals = 2): float
  {
    if ((float) $total == 0) {
      return 0.0;
    }
    return round(((float) $value / (float) $total) * 100, $decimals);
  }

  /**
   * 截断到指定小数位，不四舍五入，返回数值
   *
   * 直接舍弃超出的位数，不做进位处理。结果为整数时返回 int，否则返回 float。
   *
   * @param float $value    原始数值
   * @param int   $decimals 保留的小数位数，默认 2
   * @return int|float
   *
   * @example
   * Numeric::fixedDecimals(3.14159, 2)  // 3.14（float）
   * Numeric::fixedDecimals(3.14999, 2)  // 3.14（float，截断非四舍五入）
   * Numeric::fixedDecimals(5)           // 5（int）
   */
  static function fixedDecimals($value, $decimals = 2)
  {
    $decimals = max(0, $decimals);
    $str = sprintf("%.20f", (float) $value);
    $dot = strpos($str, '.');

    if ($dot === false) {
      $end = strlen($str);
    } else {
      $end = min($dot + 1 + $decimals, strlen($str));
    }

    $result = (float) substr($str, 0, $end);
    if ((float) (int) $result === $result) {
      return (int) $result;
    }
    return $result;
  }

  /**
   * 截断到指定小数位，不足补零，返回字符串
   *
   * 与 fixedDecimals 相同的截断逻辑，但保证始终输出 exact N 位小数。
   * 适用于价格展示等需要固定位数的字符串场景。
   *
   * @param float $value    原始数值
   * @param int   $decimals 小数位数，默认 2
   * @return string 格式化后的数字字符串
   *
   * @example
   * Numeric::padDecimals(5)         // "5.00"
   * Numeric::padDecimals(3.1)       // "3.10"
   * Numeric::padDecimals(3.14159)   // "3.14"（截断，非四舍五入）
   * Numeric::padDecimals(3.14999)   // "3.14"（截断，非四舍五入）
   * Numeric::padDecimals(9.9, 3)    // "9.900"
   */
  static function padDecimals($value, $decimals = 2): string
  {
    $decimals = max(0, $decimals);
    $str = sprintf("%.20f", (float) $value);
    $dot = strpos($str, '.');

    if ($dot === false) {
      return $str . '.' . str_repeat('0', $decimals);
    }

    $intPart = substr($str, 0, $dot);
    $decPart = substr($str, $dot + 1, $decimals);
    return $intPart . '.' . str_pad($decPart, $decimals, '0');
  }

  /**
   * 将字节数转换为人类可读格式
   *
   * @param int|float $bytes    字节数
   * @param int       $decimals 小数位数，默认 2
   * @return string 可读字符串，例如 "1.50 MB"
   *
   * @example
   * Numeric::bytesToHuman(1024)               // "1.00 KB"
   * Numeric::bytesToHuman(1572864)            // "1.50 MB"
   * Numeric::bytesToHuman(1073741824)         // "1.00 GB"
   * Numeric::bytesToHuman(0)                  // "0 B"
   */
  static function bytesToHuman($bytes, $decimals = 2): string
  {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    $bytes = (float) $bytes;
    if ($bytes == 0) {
      return '0 B';
    }

    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
      $bytes /= 1024;
      $i++;
    }

    return number_format($bytes, $decimals, '.', '') . ' ' . $units[$i];
  }
}
