<?php

namespace kernel\Middleware;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Config;
use kernel\Foundation\Store;
use kernel\Foundation\View;

class GlobalMultipleEncodeMiddleware
{
  public function handle($next)
  {
    if (Config::get("multipleEncode")) {
      $multipleEncodeJSScript = "";
      if (CHARSET === "gbk") {
        $langJson = \serialize(Store::getApp("langs"));
        if ($langJson === false) {
          $langJson = \serialize([]);
        }
        $multipleEncodeJSScript = "
<script src='source/plugin/kernel/Assets/js/unserialize.js'></script>
<script>
  const GLANG=unserialize('$langJson');
</script>
    ";
      } else {
        $langJson = \json_encode(Store::getApp("langs"));
        if ($langJson === false) {
          $langJson = \json_encode([]);
        }
        $multipleEncodeJSScript = "
<script>
  const GLANG=JSON.parse('$langJson');
</script>
    ";
      }
      if (Config::get("mode") === "development") {
        $multipleEncodeJSScript .= "
<script>
  console.log(GLANG);
</script>
          ";
      }
      View::footer($multipleEncodeJSScript);
    }
    $next();
  }
}
