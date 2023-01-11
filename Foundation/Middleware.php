<?php

namespace kernel\Foundation;

use Closure;
use kernel\Foundation\HTTP\Request;

class Middleware
{
  /**
   * 中间件基类构建函数
   *
   * @param Request $R 请求实例
   * @param Controller|AuthController|null $Controller 控制器实例，如果控制器不是类是个闭包就会传入null
   * @return \kernel\Foundation\HTTP\Response
   */
  public function __construct(Request $R, $Controller)
  {
  }
  /**
   * 中间件处理
   *
   * @param Closure $next 下一个中间件执行，会返回Response
   * @param Request $R 请求实例
   * @param Controller|AuthController|null $Controller 控制器实例，如果控制器不是类是个闭包就会传入null
   * @return \kernel\Foundation\HTTP\Response
   */
  public function handle(\Closure $next, Request $request, $Controller = null)
  {
    return $next();
  }
}
