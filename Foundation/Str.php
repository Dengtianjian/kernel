<?php

namespace kernel\Foundation;

if (!defined("F_KERNEL")) {
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
        $ar[$k] = iconv("UCS-2", \strtoupper(CHARSET), pack("H4", substr($v, -4)));
      elseif (substr($v, 0, 3) == "&#x")
        $ar[$k] = iconv("UCS-2", \strtoupper(CHARSET), pack("H4", substr($v, 3, -1)));
      elseif (substr($v, 0, 2) == "&#") {
        $ar[$k] = iconv("UCS-2", \strtoupper(CHARSET), pack("n", substr($v, 2, -1)));
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
  static function generateRandomString(int $stringLength = 5): string
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
      $string .= $charts[mt_rand(0, count($charts))];
    }
    return $string;
  }
}
