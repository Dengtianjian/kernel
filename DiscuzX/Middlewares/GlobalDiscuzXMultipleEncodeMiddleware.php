<?php

namespace kernel\DiscuzX\Middlewares;

use kernel\Foundation\Middleware\MiddlewareBase;
use kernel\DiscuzX\Foundation\DiscuzXLang;
use kernel\Foundation\Application\Applications;

/**
 * DiscuzX 多编码站点语言包下发中间件
 *
 * 把 {@see DiscuzXLang} 的整份语言字典以 JS 全局常量 `GLANG` 的形式输出给宿主插件前端。
 *
 * 安全约定（SEC-08）：统一走「JSON 数据块 + JSON.parse」通道——非 UTF-8 站点先逐项经
 * {@see diconv()} 转码，再以 HEX 转义（`< > ' & "` 全部转为 \uXXXX）json_encode，产物为
 * 纯 ASCII。语言项内容无论含引号、换行还是 `</script>`，都无法破坏脚本结构或注入代码；
 * 数据与代码分离，内容异常最多导致 GLANG 解析为空对象，不存在代码执行面。
 */
class GlobalDiscuzXMultipleEncodeMiddleware extends MiddlewareBase
{
  public function handle($next)
  {
    $res = $next();
    //* 中间件是直接 new 出来的，不走容器注入，当前应用只能这么取
    $app = Applications::default();
    if ($app !== null && $app->config()->get("multipleEncode")) {
      //* 语言字典统一走 JSON 通道：非 UTF-8 站点先逐项转码，再以 HEX 转义编码；
      //* 产物纯 ASCII——绕开 GBK 无法 json_encode 的限制，内容含引号 / </script> 也不再能注入
      $langData = DiscuzXLang::all();
      if (strtolower(CHARSET) !== "utf-8") {
        $langData = self::toUtf8($langData);
      }
      $langJson = \json_encode($langData, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT);
      if ($langJson === false) {
        $langJson = "{}";
      }
      $multipleEncodeJSScript = "
<script type='application/json' id='glang-data'>{$langJson}</script>
<script>
  const GLANG=JSON.parse(document.getElementById('glang-data').textContent||'{}');
</script>
    ";
      if ($app->isDevelopment()) {
        $multipleEncodeJSScript .= "
<script>
  console.log(GLANG);
</script>
          ";
      }
      echo $multipleEncodeJSScript;
    }
    return $res;
  }
  /**
   * 递归把语言字典转成 UTF-8
   *
   * GBK 等编码站点的语言项是站点编码字符串，直接 {@see json_encode()} 会因非法 UTF-8
   * 返回 false，因此逐项经官方 {@see diconv()} 转码。diconv 只接受字符串（传数组会
   * TypeError），故按结构递归、只对叶子字符串转码；非法字节经 iconv 的 //IGNORE 丢弃。
   *
   * @param mixed $value 语言字典（数组）或标量语言项
   * @return mixed 转码后的同构数据
   */
  private static function toUtf8($value)
  {
    if (\is_array($value)) {
      foreach ($value as $key => $item) {
        $value[$key] = self::toUtf8($item);
      }
      return $value;
    }
    if (\is_string($value) && $value !== "") {
      return \diconv($value, CHARSET, "UTF-8");
    }
    return $value;
  }
}
