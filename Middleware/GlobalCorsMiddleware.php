<?php

namespace kernel\Middleware;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Config;
use kernel\Foundation\HTTP\Response;

class GlobalCorsMiddleware
{
  public function handle($next)
  {
    $Response = $next();
    // $Response = new Response();
    if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
      $Response->null();
      return $Response;
    }
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
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
