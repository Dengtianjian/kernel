<?php

namespace kernel\Foundation;

use Closure;
use kernel\Foundation\HTTP\Request;

class Middleware
{
  /**
   * 中间件基类构建函数
   *
   * @param Closure $next
   * @param Request $R 请求
   * @return \kernel\Foundation\HTTP\Response
   */
  public function __construct(Request $R)
  {
  }
  /**
   * 中间件处理
   *
   * @param Closure $next 下一个中间件执行，会返回Response
   * @param Request $R 请求
   * @return \kernel\Foundation\HTTP\Response
   */
  public function handle(Closure $next, Request $R)
  {
    return $next();
  }
}
