<?php

namespace kernel\Foundation\Middleware;

use kernel\Foundation\App;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;

class Middleware
{
  /**
   * 全局中间件列表
   *
   * @var array
   */
  protected $middlewares = [];
  /**
   * 构建中间件管理器
   *
   */
  public function __construct() {}
  /**
   * 注册全局中间件
   *
   * @param \Closure|object|string $classOrFun 中间件类或者函数
   * @param array $executeParams 执行中间件时传入的参数
   * @return void
   */
  public function set($classOrFun, $executeParams = null)
  {
    array_push($this->middlewares, [
      "target" => $classOrFun,
      "params" => $executeParams
    ]);
  }
  /**
   * 执行中间件链（全局中间件 + 路由级中间件，洋葱模型）
   *
   * @param array|null $routeMiddlewares 路由级中间件列表
   * @param Controller $controller 控制器实例
   * @param \Closure $callback 最内层回调（执行业务逻辑）
   * @return \kernel\Foundation\HTTP\Response 中间件链最终返回的响应
   */
  public function execute($routeMiddlewares, Controller $controller, \Closure $callback)
  {
    $middlewares = $this->middlewares ?: [];
    if (is_array($routeMiddlewares) && count($routeMiddlewares)) {
      foreach ($routeMiddlewares as $routeMiddleware) {
        array_push($middlewares, [
          "target" => $routeMiddleware,
          "params" => []
        ]);
      }
    }

    return $this->executeMiddleware($middlewares, $controller, $callback);
  }
  /**
   * 递归执行中间件链
   *
   * @param array $middlewares 待执行中间件列表
   * @param Controller $controller 控制器实例
   * @param \Closure $callback 最内层回调
   * @return \kernel\Foundation\HTTP\Response
   */
  private function executeMiddleware($middlewares, Controller $controller, \Closure $callback)
  {
    if (count($middlewares) === 0)
      return $callback();

    $middleware = array_shift($middlewares);
    $next = function () use ($middlewares, $controller, $callback) {
      return $this->executeMiddleware($middlewares, $controller, $callback);
    };

    $params = $middleware['params'] ?: [];
    array_push($params, $next);

    if (is_string($middleware['target'])) {
      $middlewareInstance = new $middleware['target'](getApp()->request(), $controller);
      $executedResponse = $middlewareInstance->handle(...$params);
    } else {
      array_unshift($params, getApp()->request());
      $executedResponse = $middleware['target'](...$params);
    }

    if ($executedResponse === null) {
      throw new \RuntimeException(sprintf(
        'Middleware [%s]::handle() 未返回 Response，可能缺少 return $next()',
        is_string($middleware['target']) ? $middleware['target'] : 'Closure'
      ));
    }

    return $executedResponse;
  }
}
