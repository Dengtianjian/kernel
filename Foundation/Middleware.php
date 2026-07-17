<?php

namespace kernel\Foundation;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;

abstract class Middleware
{
  /**
   * 请求实例
   *
   * @var Request
   */
  protected $request;

  /**
   * 控制器实例
   *
   * @var Controller|AuthController|\Closure|null
   */
  protected $controller;

  /**
   * 中间件基类构造函数
   *
   * @param Request $request 请求实例
   * @param Controller|AuthController|\Closure|null $controller 控制器实例，闭包路由时为 null
   */
  public function __construct(Request $request, $controller = null)
  {
    $this->request = $request;
    $this->controller = $controller;
  }
}
