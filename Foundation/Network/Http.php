<?php

namespace kernel\Foundation\Network;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/**
 * HTTP
 * 
 * @deprecated <0.3.5.20230218.1105
 */
class Http
{
  /**
   * 获取用户IP地址
   *
   * @return string IP地址
   */
  public static function realClientIp()
  {
    $ip = null;
    if (getenv("HTTP_CLIENT_IP")) {
      $ip = getenv("HTTP_CLIENT_IP");
    } else if (getenv("HTTP_X_FORWARDED_FOR")) {
      $ip = getenv("HTTP_X_FORWARDED_FOR");
    } else if (getenv("REMOTE_ADDR")) {
      $ip = getenv("REMOTE_ADDR");
    }
    return $ip;
  }
}