<?php

namespace kernel\Middleware;

use kernel\Foundation\Config;
use kernel\Foundation\Dashboard;
use kernel\Foundation\GlobalVariables;
use kernel\Foundation\Router;
use kernel\App\Dashboard\Controller as DashboardController;

class GlobalSetsMiddleware
{
  public function handle($next)
  {
    if (Config::get("globalSets")&&count(Config::get("globalSets")) > 0) {
      GlobalVariables::set([
        "_GG" => [
          // "sets" => Dashboard::getSetValue(Config::get("globalSets"))
        ]
      ]);
    } else {
      GlobalVariables::set([
        "_GG" => [
          "sets" => []
        ]
      ]);
    }
    Router::get("_set", DashboardController\GetSetController::class);
    $next();
  }
}
