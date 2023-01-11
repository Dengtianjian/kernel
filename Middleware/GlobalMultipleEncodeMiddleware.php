<?php

namespace gstudio_kernel\Middleware;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Config;
use gstudio_kernel\Foundation\Store;
use gstudio_kernel\Foundation\View;

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
<script src='source/plugin/gstudio_kernel/Assets/js/unserialize.js'></script>
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
