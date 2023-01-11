<?php

namespace gstudio_kernel\Foundation;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

class Output
{
  static function debug(...$data)
  {
    self::format(...$data);
    exit;
  }
  static function backtrace($options = DEBUG_BACKTRACE_PROVIDE_OBJECT,  $limit = 0)
  {
    $stack = debug_backtrace($options, $limit);
    self::debug($stack);
  }
  static function printContent($outputString, ...$value)
  {
    if (is_string($outputString)) {
      printf($outputString, ...$value);
    } else {
      print_r($outputString);
    }
  }
  static function format(...$data)
  {
    echo "<pre>";
    foreach ($data as $dataItem) {
      print_r($dataItem);
      echo "<br/>";
    }
    echo "</pre>";
  }
}
