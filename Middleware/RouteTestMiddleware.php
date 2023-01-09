<?php

namespace kernel\Middleware;

use kernel\Foundation\HTTP\Request;

class RouteTestMiddleware
{
  public function __construct($next, Request $R)
  {
    print_r("r3");
    return $next();
    print_r("r4");
  }
}
