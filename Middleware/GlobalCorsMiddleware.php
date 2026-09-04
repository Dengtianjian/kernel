<?php

namespace kernel\Middleware;

use kernel\Foundation\HTTP\Cors;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Middleware\MiddlewareBase;

/**
 * 全局 CORS 中间件
 *
 * 职责：仅「补充响应头」，不拦截请求、不短路 OPTIONS。所有 CORS 响应头
 * （Access-Control-*）的解析与装配逻辑统一由 kernel\Foundation\HTTP\Cors 完成，
 * 本类仅做薄封装：取请求 Origin → 委托 Cors::applyTo() 注入响应头。
 *
 * 执行时机：本中间件为「后置」中间件——先 $next() 执行后续逻辑拿到 Response，
 * 再统一注入 CORS 头。因是后置，不会阻断任何请求。
 *
 * 关于预检（OPTIONS）：CORS 预检请求不再由框架统一拦截，交由业务应用自行处理
 * （注册 options 路由返回 204/空体，或倚赖本中间件为响应补 CORS 头）。若未来需要
 * 在框架层「按 origin 短路」预检，需将本中间件改为前置中间件。
 *
 * 配置键（cors.*，默认值见 Cors::DEFAULTS）。
 */
class GlobalCorsMiddleware extends MiddlewareBase
{
  /**
   * 获取请求的 Origin（仅取自 HTTP_ORIGIN 头）
   *
   * 无该头（同源请求、非浏览器、服务器间调用等）视为非跨域，返回 null。
   *
   * @return string|null
   */
  public function getOrigin(): ?string
  {
    return $_SERVER['HTTP_ORIGIN'] ?? null;
  }

  /**
   * 中间件处理：为响应补充 CORS 头
   *
   * 后置中间件——先执行后续逻辑拿到 Response，再委托 Cors 注入跨域头。
   *
   * @param \Closure $next
   * @return \kernel\Foundation\HTTP\Response
   */
  public function handle($next): Response
  {
    // 后置中间件：先执行后续逻辑，再补充 CORS 响应头（不拦截、不短路）
    $Response = $next();
    return Cors::applyTo($Response, $this->getOrigin());
  }
}
