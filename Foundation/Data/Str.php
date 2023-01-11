<?php

namespace gstudio_kernel\Foundation\Data;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class Str
{
  static function unescape($str)
  {
    $str = rawurldecode($str);
    preg_match_all("/%u.{4}|&#x.{4};|&#\d+;|.+/U", $str, $r);
    $ar = $r[0];
    foreach ($ar as $k => $v) {
      if (substr($v, 0, 2) == "%u")
        $ar[$k] = iconv("UCS-2", \strtoupper("UTF-8"), pack("H4", substr($v, -4)));
      elseif (substr($v, 0, 3) == "&#x")
        $ar[$k] = iconv("UCS-2", \strtoupper("UTF-8"), pack("H4", substr($v, 3, -1)));
      elseif (substr($v, 0, 2) == "&#") {
        $ar[$k] = iconv("UCS-2", \strtoupper("UTF-8"), pack("n", substr($v, 2, -1)));
      }
    }
    return join("", $ar);
  }
  static function replaceParams($string, $params = [])
  {
    \preg_match_all("/(?<=\{)\w+(?=\})/i", $string, $paramKeys);
    if (count($paramKeys) > 0) {
      $paramKeys = $paramKeys[0];
      foreach ($paramKeys as &$item) {
        $item = "{" . $item . "}";
      }
      $string = \str_replace($paramKeys, $params, $string);
    }
    return $string;
  }
  /**
   * 生成随机字符串
   *
   * @param integer $stringLength 生成的字符串长度
   * @return string
   */
  static function generateRandomString($stringLength = 5)
  {
    $charts = array(
      'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
      'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
      't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
      'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
      'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
      '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'
    );
    $string = "";
    for ($i = 0; $i < $stringLength; $i++) {
      $randomIndex = mt_rand(0, count($charts));
      if (isset($charts[$randomIndex])) {
        $string .= $charts[$randomIndex];
      }
    }
    return $string;
  }
  /**
   * 以微妙为单位的种子生成随机数字
   *
   * @param integer $min 可选的、返回的最小值（默认：0）
   * @param integer $max 可选的、返回的最大值（默认：mt_getrandmax()）
   * @return integer
   */
  static function generateRandomNumbers($min = 0, $max)
  {
    list($usec, $sec) = explode(' ', microtime());
    $seed = $sec + $usec * 1000000;
    mt_srand($seed);
    return mt_rand($min, $max);
  }
}
