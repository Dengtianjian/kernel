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
 * 执行时机：
 * - 非开发模式：**后置**——先 $next() 执行后续逻辑拿到 Response，再 Cors::applyTo() 注入头。
 * - 开发模式：**前置**——先 Cors::emit() 输出头再 $next()。业务抛异常时响应不经过后置逻辑，
 *   前置输出可让错误响应也带 CORS 头；同时开发模式下来源不限制（Cors::isDevelopment()）。
 *
 * 关于预检（OPTIONS）：CORS 预检请求不再由框架统一拦截，交由业务应用自行处理
 * （注册 options 路由返回 204/空体，或倚赖本中间件为响应补 CORS 头）。
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
  public function getOrigin()
  {
    return $_SERVER['HTTP_ORIGIN'] ?? null;
  }

  /**
   * 中间件处理：为响应补充 CORS 头
   *
   * - 开发模式（mode=development）：**前置**输出 CORS 头。业务抛异常时响应不经过本中间件
   *   后置逻辑（$next() 抛错即中断），前置输出可保证错误响应同样带 CORS 头，否则浏览器会以
   *   跨域错误拦截、只看到空白。开发模式下来源不限制（见 Cors::isDevelopment()）。
   * - 非开发模式：保持**后置**注入，先执行后续逻辑拿到 Response 再委托 Cors 注入。
   *
   * @param \Closure $next
   * @return \kernel\Foundation\HTTP\Response
   */
  public function handle($next): Response
  {
    //* 开发模式：前置输出 CORS 头（错误响应也能带上，便于本地调试看到报错）；来源不限制
    if (Cors::isDevelopment()) {
      Cors::emit($this->getOrigin());
      return $next();
    }

    //* 非开发模式：后置注入（不拦截、不短路；来源仍受 cors.allowOrigin 白名单限制）
    $Response = $next();
    return Cors::applyTo($Response, $this->getOrigin());
  }
}
