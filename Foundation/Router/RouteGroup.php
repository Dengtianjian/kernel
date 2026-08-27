<?php

namespace kernel\Foundation\Router;

class RouteGroup extends RouteRegister
{
  function __construct($prefix, $callback, $same = null, $group = null)
  {
    $this->prefix = $prefix;
    $this->same = $same;
    $this->group = $group;
    $callback($this);
  }

  /**
   * 嵌套子组（组中组）+ 读取所属组（双语义，兼容父类 getter）
   *
   * - 无参调用：读取当前组所属的父组（继承 RouteRegister::group() getter 语义）
   * - 传 prefix + callback：创建子组，子组前缀在当前组前缀之上叠加（mergePath），
   *   生成的子 RouteGroup 作为纯容器供其内部叶子路由通过 group 位引用，
   *   resolvedUri() 取子组 prefix 即得完整前缀。
   *
   * ```
   * Route::group('api', function ($g) {
   *   $g->group('v1', function ($g2) {
   *     $g2->get('users'); // uri = api/v1/users
   *   });
   * });
   * ```
   *
   * @param string|null $prefix 子组前缀（相对当前组）
   * @param callable|null $callback 子组回调（注入子 RouteGroup）
   * @return RouteGroup|mixed 无参返回所属父组；传值返回新建子组
   */
  function group($prefix = null, $callback = null)
  {
    if ($prefix === null && $callback === null) {
      return $this->group;
    }
    // 传入相对 prefix 与父组/外层 same，完整前缀由 resolvedUri() 沿祖先链统一拼接
    return new RouteGroup($prefix, $callback, $this->same, $this);
  }

  /**
   * 组内含 same（same 中再分 HTTP 方法）+ 读取所属 same（双语义）
   *
   * - 无参调用：读取当前组所属的 same（交叉嵌套时才有值）
   * - 传 uri + callback：在组内创建同 URI 注册器，生成的 RouteSame 通过其 group 位
   *   引用当前组，resolvedUri() 组合「组前缀 + same 共用 URI + 叶子 URI」。
   *
   * ```
   * Route::group('api', function ($g) {
   *   $g->same('users', function ($rs) {
   *     $rs->get(ShowUserController::class); // uri = api/users
   *   });
   * });
   * ```
   *
   * @param string|null $uri 同 URI 基础
   * @param callable|null $callback 同 URI 回调（注入子 RouteSame）
   * @return RouteSame|mixed 无参返回所属 same；传值返回新建同 URI 注册器
   */
  function same($uri = null, $callback = null)
  {
    if ($uri === null && $callback === null) {
      return $this->same;
    }
    return new RouteSame($uri, $callback, $this->same, $this);
  }

  /**
   * 组内叶子 DSL 公共构造：生成归属当前组的路由载体
   *
   * controller 为 null 时不调用 ->controller()，避免吞掉实例（返回 null），
   * 使叶子 controller 留空、由 resolve() 沿祖先链继承。
   */
  private function leaf($uri, $method, $controller)
  {
    $r = new RouteRegister($uri, $method, $this, $this->same);
    if ($controller !== null) {
      $r->controller($controller);
    }
    Routes::push($r);
    return $r;
  }

  function get($uri = null, $controller = null)
  {
    return $this->leaf($uri, "get", $controller);
  }
  function post($uri = null, $controller = null)
  {
    return $this->leaf($uri, "post", $controller);
  }
  function put($uri = null, $controller = null)
  {
    return $this->leaf($uri, "put", $controller);
  }
  function patch($uri = null, $controller = null)
  {
    return $this->leaf($uri, "patch", $controller);
  }
  function delete($uri = null, $controller = null)
  {
    return $this->leaf($uri, "delete", $controller);
  }
  function head($uri = null, $controller = null)
  {
    return $this->leaf($uri, "head", $controller);
  }
  function options($uri = null, $controller = null)
  {
    return $this->leaf($uri, "options", $controller);
  }
  function any($uri = null, $controller = null)
  {
    return $this->leaf($uri, "*", $controller);
  }

  /**
   * 组内 fallback 兜底路由（该组下所有正常路由未命中时命中）
   *
   * 不限定方法/URI；叶子以当前组为其 group 位，resolve() 沿祖先链继承
   * domain / prefix 等。可配合 Route::domain 声明域名组内专属兜底。
   *
   * @param string|array|\Closure|null $controller 兜底控制器/闭包
   * @return RouteRegister
   */
  function fallback($controller = null)
  {
    $r = new RouteRegister("", "*", $this, $this->same);
    $r->fallback(true);
    if ($controller !== null) {
      $r->controller($controller);
    }
    Routes::push($r);
    return $r;
  }
}
