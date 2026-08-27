<?php

namespace kernel\Foundation\Middleware;

use kernel\Foundation\App;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;

class Middleware
{
  /**
   * 全局中间件列表（关联数组：键名 => 中间件定义）
   *
   * 键名规则：
   * - 有别名用别名
   * - 否则用类路径（字符串类名）
   * - 闭包用对象 id（spl_object_id）
   *
   * @var array<string, array{target: string|\Closure, params: array}>
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
   * @param string|null $alias 中间件别名（可选，用于键名和路由引用）
   * @return void
   */
  public function set($classOrFun, $executeParams = null, $alias = null)
  {
    $key = $this->resolveKey($classOrFun, $alias);
    $this->middlewares[$key] = [
      "target" => $classOrFun,
      "params" => $executeParams
    ];
  }

  /**
   * 生成中间件键名
   *
   * 优先级：别名 > 类路径 > 闭包对象 id
   *
   * @param \Closure|object|string $classOrFun 中间件定义
   * @param string|null $alias 别名
   * @return string 键名
   */
  protected function resolveKey($classOrFun, $alias = null)
  {
    if ($alias !== null) {
      return $alias;
    }
    if (is_string($classOrFun)) {
      return $classOrFun;
    }
    if ($classOrFun instanceof \Closure) {
      return 'closure_' . spl_object_id($classOrFun);
    }
    // 对象实例
    return get_class($classOrFun) . '_' . spl_object_id($classOrFun);
  }
  /**
   * 执行中间件链（全局中间件 + 路由级中间件，洋葱模型）
   *
   * @param array|null $routeMiddlewares 路由级中间件列表（类名或别名）
   * @param Controller $controller 控制器实例
   * @param \Closure $callback 最内层回调（执行业务逻辑）
   * @return \kernel\Foundation\HTTP\Response 中间件链最终返回的响应
   */
  public function execute($routeMiddlewares, Controller $controller, \Closure $callback)
  {
    $middlewares = $this->middlewares ?: [];
    if (is_array($routeMiddlewares) && count($routeMiddlewares)) {
      foreach ($routeMiddlewares as $routeMiddleware) {
        // 按键名查找（别名或类名），未找到则视为类名
        if (isset($this->middlewares[$routeMiddleware])) {
          $middlewares[$routeMiddleware] = $this->middlewares[$routeMiddleware];
        } elseif (is_string($routeMiddleware) && class_exists($routeMiddleware)) {
          $middlewares[$routeMiddleware] = [
            "target" => $routeMiddleware,
            "params" => []
          ];
        } else {
          throw new \InvalidArgumentException(sprintf(
            '路由中间件 [%s] 未注册且类不存在',
            $routeMiddleware
          ));
        }
      }
    }

    return $this->executeMiddleware($middlewares, $controller, $callback);
  }
  /**
   * 递归执行中间件链
   *
   * @param array $middlewares 待执行中间件列表（关联数组）
   * @param Controller $controller 控制器实例
   * @param \Closure $callback 最内层回调
   * @return \kernel\Foundation\HTTP\Response
   */
  private function executeMiddleware($middlewares, Controller $controller, \Closure $callback)
  {
    if (count($middlewares) === 0)
      return $callback();

    // 取第一个中间件（保持关联数组键名）
    $key = array_key_first($middlewares);
    $middleware = $middlewares[$key];
    unset($middlewares[$key]);

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
        $key
      ));
    }

    return $executedResponse;
  }
}
