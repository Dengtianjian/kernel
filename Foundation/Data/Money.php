<?php

namespace kernel\Foundation\Data;


/**
 * 货币工具类
 *
 * 提供元/分互转、折扣、税费、中文大写等货币相关静态方法。全部方法无需实例化即可调用。
 *
 * 方法分为以下几组：
 * - 转换：       fenToYuan、yuanToFen、yuan
 * - 计算：       discount、tax
 * - 格式化：     toChinese
 *
 * @package kernel\Foundation\Data
 */
class Money
{
  // ========================
  //  转换
  // ========================

  /**
   * 分转元
   *
   * @param int|float $fen 金额（分）
   * @return float 金额（元）
   *
   * @example
   * Money::fenToYuan(123)    // 1.23
   * Money::fenToYuan(0)      // 0.0
   * Money::fenToYuan(10050)  // 100.5
   */
  static function fenToYuan($fen): float
  {
    return (float) $fen / 100;
  }

  /**
   * 元转分
   *
   * 不四舍五入：截断到分，超出 2 位小数的部分直接舍弃。
   *
   * @param int|float $yuan 金额（元）
   * @return int 金额（分）
   *
   * @example
   * Money::yuanToFen(1.23)    // 123
   * Money::yuanToFen(1.239)   // 123（截断，非四舍五入）
   * Money::yuanToFen(0.5)     // 50
   * Money::yuanToFen(100)     // 10000
   */
  static function yuanToFen($yuan): int
  {
    $str = (string) (float) $yuan;
    $dot = strpos($str, '.');
    if ($dot === false) {
      return (int) $str * 100;
    }
    $intPart = substr($str, 0, $dot);
    $decPart = substr($str, $dot + 1, 2);
    $decPart = str_pad($decPart, 2, '0');
    return (int) ($intPart . $decPart);
  }

  /**
   * 元转显示字符串
   *
   * 输出带 ¥ 符号、固定 2 位小数的字符串，适合直接渲染到页面。
   *
   * @param int|float $yuan 金额（元）
   * @return string 如 "¥1.23"
   *
   * @example
   * Money::yuan(1.23)   // "¥1.23"
   * Money::yuan(0)      // "¥0.00"
   * Money::yuan(100.5)  // "¥100.50"
   */
  static function yuan($yuan): string
  {
    return '¥' . number_format((float) $yuan, 2, '.', '');
  }

  // ========================
  //  计算
  // ========================

  /**
   * 折扣计算
   *
   * 按百分比计算折后金额，截断到分（不四舍五入）。
   *
   * @param int|float $yuan    原价（元）
   * @param int|float $percent 折扣，如 8.5 表示 8.5 折
   * @return float 折后价（元）
   *
   * @example
   * Money::discount(9.99, 8.5)  // 8.49（9.99 * 8.5 / 10 = 849.15分 → 截断 849分 → 8.49元）
   * Money::discount(10, 7)      // 7.0
   * Money::discount(1, 0)       // 0.0
   */
  static function discount($yuan, $percent): float
  {
    $fen = self::yuanToFen($yuan);
    $resultFen = (int) ((float) $fen * (float) $percent / 10);
    return self::fenToYuan($resultFen);
  }

  /**
   * 税费计算
   *
   * 按税率计算税费，截断到分（不四舍五入）。
   *
   * @param int|float $yuan 金额（元）
   * @param int|float $rate 税率百分比，如 6 表示 6%，13 表示 13%
   * @return float 税费（元）
   *
   * @example
   * Money::tax(100, 6)     // 6.0
   * Money::tax(9.99, 6)    // 0.59（999 * 6 / 100 = 59.94分 → 截断 59分 → 0.59元）
   * Money::tax(50, 13)     // 6.5
   */
  static function tax($yuan, $rate): float
  {
    $fen = self::yuanToFen($yuan);
    $resultFen = (int) ((float) $fen * (float) $rate / 100);
    return self::fenToYuan($resultFen);
  }

  // ========================
  //  格式化
  // ========================

  /**
   * 转中文大写金额
   *
   * 将元为单位的金额转为财务中文大写，符合发票、合同等正式场景。
   *
   * 规则：
   * - 元后无角分 → 加"整"
   * - 有角无分 → 末尾不补
   * - 角为零分非零 → 角位补"零"
   *
   * @param int|float $yuan 金额（元）
   * @return string 如 "壹拾贰元叁角肆分"
   *
   * @example
   * Money::toChinese(0)              // "零元整"
   * Money::toChinese(1.23)           // "壹元贰角叁分"
   * Money::toChinese(12)             // "壹拾贰元整"
   * Money::toChinese(12.03)          // "壹拾贰元零叁分"
   * Money::toChinese(12.3)           // "壹拾贰元叁角"
   * Money::toChinese(1012.03)        // "壹仟零壹拾贰元零叁分"
   */
  static function toChinese($yuan): string
  {
    $fen = self::yuanToFen($yuan);

    if ($fen <= 0) {
      return '零元整';
    }

    $digits = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];

    $yuan = intdiv($fen, 100);
    $jiao = intdiv($fen % 100, 10);
    $remainFen = $fen % 10;

    // 整数部分（元）
    $result = $yuan > 0 ? self::_integerToChinese($yuan, $digits) . '元' : '';

    // 角分
    if ($jiao === 0 && $remainFen === 0) {
      return $result . '整';
    }

    if ($jiao > 0) {
      $result .= $digits[$jiao] . '角';
    }
    if ($remainFen > 0) {
      if ($jiao === 0 && $yuan > 0) {
        $result .= '零';
      }
      $result .= $digits[$remainFen] . '分';
    }

    return $result;
  }

  // ========================
  //  私有辅助
  // ========================

  /**
   * 整数部分转中文大写
   *
   * 采用逐位遍历法：将数字按 4 位一组（万进制）从高位向低位逐位处理，
   * 自动在跨段时插入"零"和万/亿等单位。
   *
   * @param int   $num    非负整数
   * @param array $digits 数字中文映射
   * @return string
   */
  private static function _integerToChinese(int $num, array $digits): string
  {
    if ($num === 0) {
      return $digits[0];
    }

    $units = ['', '拾', '佰', '仟'];
    $bigUnits = ['', '万', '亿', '万亿', '万万亿'];

    $str = (string) $num;
    $len = strlen($str);

    // 补齐到 4 的倍数，便于统一处理
    $padLen = (int) ceil($len / 4) * 4;
    $str = str_pad($str, $padLen, '0', STR_PAD_LEFT);

    $result = '';
    $zero = false;

    for ($i = 0; $i < $padLen; $i++) {
      $d = (int) $str[$i];
      $pos = ($padLen - $i - 1) % 4;       // 当前位在段内的位置：3=仟, 2=佰, 1=拾, 0=个
      $segIdx = intdiv($padLen - $i - 1, 4); // 段索引：0=个段, 1=万段, 2=亿段

      if ($d === 0) {
        $zero = true;
      } else {
        if ($zero && $result !== '') {
          $result .= $digits[0];
        }
        $zero = false;
        $result .= $digits[$d] . $units[$pos];
      }

      // 段尾（个位），决定是否追加万/亿等大单位
      if ($pos === 0 && $segIdx > 0) {
        $segStart = $i - 3;
        $segStr = substr($str, $segStart, 4);
        if ((int) $segStr > 0) {
          $result .= $bigUnits[$segIdx];
        }
        $zero = false; // 新段开始，重置零标记
      }
    }

    // "壹拾" → "拾"（财务惯例：拾前面不写壹）
    if (strpos($result, '壹拾') === 0) {
      $result = substr($result, strlen('壹'));
    }

    return $result;
  }
}
