<?php

namespace kernel\Foundation;

class Output
{
  static function debug(...$data)
  {
    echo "<pre>";
    foreach ($data as $dataItem) {
      print_r($dataItem);
      echo "<br/>";
    }
    echo "</pre>";
    exit;
  }
  static function backtrace(int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT, int $limit = 0)
  {
    $stack = debug_backtrace($options, $limit);
    self::debug($stack);
  }
  static function print($outputString, ...$value)
  {
    if (is_string($outputString)) {
      printf($outputString, ...$value);
    } else {
      print_r($outputString);
    }
  }
}
