<?php

namespace kernel\Foundation;

use Closure;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\HTTP\Request;

class Middleware
{
  /**
   * 请求实例
   *
   * @var Request
   */
  protected $request = null;
  /**
   * 控制器
   *
   * @var Controller|AuthController|Closure
   */
  protected $controller = null;
  /**
   * 中间件基类构建函数
   *
   * @param Request $R 请求实例
   * @param Controller|AuthController|null $Controller 控制器实例，如果控制器不是类是个闭包就会传入null
   * @return \kernel\Foundation\HTTP\Response
   */
  public function __construct($request, $controller)
  {
    $this->request = $request;
    $this->controller = $controller;
  }
}
