<?php

namespace kernel\Middleware;

use kernel\Foundation\Config;
use kernel\Foundation\GlobalVariables;
use kernel\Foundation\View;

class GlobalMultipleEncodeMiddleware
{
  public function handle($next)
  {
    if (Config::get("multipleEncode")) {
      $multipleEncodeJSScript = "";
      if (CHARSET === "gbk") {
        $langJson = \serialize(GlobalVariables::get("_GG/langs"));
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
        $langJson = \json_encode(GlobalVariables::get("_GG/langs"));
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
