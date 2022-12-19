<?php

namespace gstudio_kernel\Foundation;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

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
}
