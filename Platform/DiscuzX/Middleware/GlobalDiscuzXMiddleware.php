<?php

namespace kernel\Platform\DiscuzX\Middleware;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Middleware;
use kernel\Foundation\Output;
use kernel\Platform\DiscuzX\Foundation\DiscuzXViewResponse;

class GlobalDiscuzXMiddleware extends Middleware
{
  public function handle($next, Request $R)
  {
    return $next();
  }
}
