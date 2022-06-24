<?php

namespace kernel\Middleware;

use kernel\Foundation\Config;
use kernel\Foundation\Output;
use kernel\Foundation\Request;
use kernel\Foundation\Response;

class GlobalCorsMiddleware
{
  public function handle($next)
  {
    if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
      Response::null();
    }
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowOrigin = Config::get("cors/allowOrigin");
    if (is_array($allowOrigin)) {
      if (in_array($origin, $allowOrigin)) {
        Response::header("Access-Control-Allow-Origin", $origin);
      } else {
        Response::header("Access-Control-Allow-Origin", "*");
      }
    } else if ($allowOrigin !== "*" && $allowOrigin !== $origin) {
      Response::header("Access-Control-Allow-Origin", "");
    }

    Response::header("Access-Control-Allow-Headers", implode(",", Config::get("cors/allowHeaders") ?? []));
    Response::header("Access-Control-Expose-Headers", implode(",", Config::get("cors/exposeHeaders") ?? []));
    Response::header("Access-Control-Max-Age", Config::get("cors/maxAge") ?? 86400);
    $next();
  }
}
