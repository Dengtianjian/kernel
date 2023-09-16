<?php

namespace kernel\Middleware;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Config;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Middleware;

class GlobalCorsMiddleware extends Middleware
{
  /**
   * 获取请求来源
   *
   * @return string
   */
  public function getOrigin()
  {
    $origin = null;
    if (array_key_exists('HTTP_ORIGIN', $_SERVER)) {
      $origin = $_SERVER['HTTP_ORIGIN'];
    } else  if (array_key_exists('HTTP_REFERER', $_SERVER)) {
      $origin = $_SERVER['HTTP_REFERER'];
    } else {
      $origin = $_SERVER['REMOTE_ADDR'];
    }

    return $origin;
  }
  public function handle($next)
  {
    $Response = $next();
    if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
      $Response->null();
      return $Response;
    }
    $origin = $this->getOrigin();
    $allowOrigin = Config::get("cors/allowOrigin");
    if (!is_array($allowOrigin)) {
      if ($allowOrigin !== "*") {
        $allowOrigin = array_map(function ($item) {
          return trim($item);
        }, explode(",", $allowOrigin));
      }
    }
    if ($allowOrigin === "*") {
      $Response->header("Access-Control-Allow-Origin", $origin);
    } else {
      if (in_array($origin, $allowOrigin)) {
        $Response->header("Access-Control-Allow-Origin", $origin);
      } else {
        $Response->header("Access-Control-Allow-Origin", "");
      }
    }

    $Response->header("Access-Control-Allow-Headers", implode(",", Config::get("cors/allowHeaders") ?: ["Authorization"]));
    $Response->header("Access-Control-Expose-Headers", implode(",", Config::get("cors/exposeHeaders") ?: ["Authorization"]));
    $Response->header("Access-Control-Max-Age", Config::get("cors/maxAge") ?: 86400);
    return $Response;
  }
}
