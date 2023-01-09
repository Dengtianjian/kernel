<?php

namespace kernel\Middleware;

use kernel\Foundation\HTTP\Request;

class GlobalTestMiddleware
{
  public function __construct($next, Request $R, $a, $b)
  {
    print_r(3);
    $next();
    print_r(4);
  }
}
