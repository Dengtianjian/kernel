<?php

namespace kernel\Foundation;

class Date
{
  public static function microseconds()
  {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }
  public static function milliseconds()
  {
    list($usec, $sec) = explode(" ", microtime());
    return ((int)substr($usec, 2, 3) + $sec * 1000);
  }
}
