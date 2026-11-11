<?php

namespace kernel\DiscuzX\Middlewares;

use kernel\Foundation\Middleware\MiddlewareBase;
use kernel\DiscuzX\Foundation\DiscuzXLang;
use kernel\Foundation\Application\Applications;

class GlobalDiscuzXMultipleEncodeMiddleware extends MiddlewareBase
{
  public function handle($next)
  {
    $res = $next();
    //* 中间件是直接 new 出来的，不走容器注入，当前应用只能这么取
    $app = Applications::default();
    if ($app !== null && $app->config()->get("multipleEncode")) {
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
      if ($app->isDevelopment()) {
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
