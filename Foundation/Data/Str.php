<?php

namespace kernel\Foundation\Data;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Str
{
  /**
   * 可对前端通过escape()编码的字符进行解码
   *
   * @param string $str 要解码的字符串
   * @return string
   */
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
  static function generateRandomNumbers($min = 0, $max = 100)
  {
    if (function_exists("random_int")) {
      list($usec, $sec) = explode(' ', microtime());
      $seed = $sec + $usec * 1000000;
      mt_srand($seed);
      return random_int($min, $max);
    }
    return mt_rand($min, $max);
  }
  /**
   * 生成序列单号
   *
   * @param integer $ExpectLength 期望的长度
   * @param string|int|double $Prefix 前缀
   * @param string|int|double $Suffix 后缀
   * @return string 序列单号
   */
  static function generateSerialNumber($ExpectLength = 32, $Prefix = null, $Suffix = null)
  {
    $length = $Prefix && (is_string($Prefix) || is_numeric($Prefix)) ? $ExpectLength - strlen(strval($Prefix)) : $ExpectLength;
    $length = $Suffix && (is_string($Suffix) || is_numeric($Suffix)) ? $length - strlen(strval($Suffix)) : $length;
    $NowTimeString = strval(time());

    $SplitTimes = str_split($NowTimeString);
    $no = [date("YmdHis")];
    for ($i = 0; $i < 1; $i++) {
      for ($i = 0; $i < count($SplitTimes); $i++) {
        array_push($no, $SplitTimes[mt_rand(0, 9)]);
      }
    }
    $no = implode("", $no);
    if (strlen($no) > $length) {
      $no = substr($no, strlen($no) - $length);
    } else if (strlen($no) < $length) {
      $min = "1";
      $max = "9";
      $FilledLength = $length - strlen($no);
      for ($i = 1; $i < $FilledLength; $i++) {
        $min .= "0";
        $max .= "9";
      }

      $nonceStr = self::generateRandomNumbers(intval($min), intval($max));
      $nonceStr = substr($nonceStr, 0, $FilledLength);
      $no = implode("", [$no, $nonceStr]);
    }

    return implode("", [$Prefix, $no, $Suffix]);
  }
  /**
   * XML字符串转数组
   *
   * @param string $XMLString xml字符串
   * @return array|false
   */
  static function xmlToArray($XMLString)
  {
    $options = 0;
    if (strpos($XMLString, "CDATA") !== false) {
      $options = LIBXML_NOCDATA;
    }
    $toObject = simplexml_load_string($XMLString, "SimpleXMLElement", $options);
    if ($toObject == false) {
      return false;
    }
    return json_decode(json_encode($toObject), true);
  }
}
