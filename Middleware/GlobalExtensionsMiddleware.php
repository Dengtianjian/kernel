<?php

namespace kernel\Middleware;

if (!defined("F_KERNEL")) {
  exit("Access Denied");
}

use kernel\Foundation\Config;
use kernel\Foundation\Dashboard;
use kernel\Foundation\Lang;
use kernel\Foundation\Request;

class GlobalExtensionsMiddleware
{
  private $uris = [
    "_extensions",
  ];
  function handle($next, Request $request)
  {
    if (Config::get("dashboard/use")) {
      $navs = [
        "_extensions" => [
          "nav_id" => "_extensions",
          "nav_up" => 0,
          "nav_uri" => "_extensions",
          "nav_name" => Lang::value("kernel/extension"),
          "nav_sort" => 99,
          "nav_custom" => 1
        ]
      ];
      Dashboard::customNav([$navs['_extensions']]);
    }

    $next();
  }
}
