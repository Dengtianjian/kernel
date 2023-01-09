<?php

namespace kernel\Middleware;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Middleware;
use kernel\Foundation\Output;
use kernel\Platform\DiscuzX\Foundation\DiscuzXViewResponse;

class GlobalDiscuzXMiddleware extends Middleware
{
  public function handle($next, Request $R)
  {
    $res = $next();
    if ($res->statusCode() > 299) {
      $res = new DiscuzXViewResponse("section");
    }
    return $res;
  }
}
