<?php

namespace kernel\Modules\DiscuzX\Middleware;

use kernel\Foundation\Config;
use kernel\Foundation\Middleware\MiddlewareBase;
use kernel\Modules\DiscuzX\Foundation\DiscuzXLang;

class GlobalDiscuzXMultipleEncodeMiddleware extends MiddlewareBase
{
  public function handle($next)
  {
    $res = $next();
    if (Config::get("multipleEncode")) {
      $multipleEncodeJSScript = "";
      if (CHARSET === "gbk") {
        $langJson = \serialize(DiscuzXLang::all());
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
        $langJson = \json_encode(DiscuzXLang::all());
        if ($langJson === false) {
          $langJson = \json_encode([]);
        }
        $multipleEncodeJSScript = "
<script>
  const GLANG=JSON.parse('$langJson');
</script>
    ";
      }
      if (Config::isDevelopment()) {
        $multipleEncodeJSScript .= "
<script>
  console.log(GLANG);
</script>
          ";
      }
      print_r($multipleEncodeJSScript);
    }
    return $res;
  }
}
