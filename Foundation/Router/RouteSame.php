<?php

namespace kernel\Foundation\Router;

/**
 * 同 URI 路由注册器（same() 回调首参）
 *
 * Route::same() 会把共用 URI 交给本实例并作为回调的第一个参数注入，
 * 用于在同一 URI 下以链式方式注册不同 HTTP 方法的控制器。
 *
 * ```
 * Route::same('links/{id}', function (RouteSame $rs) {
 *   $rs->get(ShowLinkController::class);      // GET    /links/{id}
 *   $rs->put(UpdateLinkController::class);    // PUT    /links/{id}
 *   $rs->delete(RemoveLinkController::class); // DELETE /links/{id}
 * });
 * ```
 *
 * 支持「same 中 same」（same 的嵌套）：子 same 的 URI 在当前 same 的 URI 之上
 * 叠加（mergePath），并继承父 same 的中间件 / 参数 / 名称 / 控制器等属性。
 *
 * ```
 * Route::same('users', function ($rs) {
 *   $rs->same('{id}', function ($rs2) {
 *     $rs2->get(ShowUserController::class);      // GET /users/{id}
 *     $rs2->put(UpdateUserController::class);    // PUT /users/{id}
 *   });
 * });
 * ```
 *
 * 独立于 RouteGroup：RouteGroup 是「路由组」，DSL 收 uri + controller；RouteSame 是
 * 「同 URI 注册器」，共用 URI 已由构造给定，DSL 只收 controller。两者方法同名但
 * 参数语义不同，无法在覆写时兼容，故 RouteSame 不复用 RouteGroup 的 DSL，
 * 直接继承 RouteRegister 独立定义。
 */
class RouteSame extends RouteRegister
{
  /**
   * @param string $uri 共用 URI
   * @param \Closure $callback 注册回调（首参为当前 RouteSame 实例）
   * @param RouteSame|null $same 父级同 URI 注册器（嵌套 same 时传入）
   * @param RouteGroup|null $group 所属组（group 内含 same 时传入）
   */
  function __construct($uri, $callback, $same = null, $group = null)
  {
    $this->same = $same;
    $this->group = $group;
    // 保留相对 URI 段，最终完整路径由 resolvedUri() 沿祖先链按嵌套深度统一拼接
    $this->uri = $uri;
    $callback($this);
  }

  /**
   * 嵌套子 same + 读取父级 same（双语义，兼容父类 getter）
   *
   * - 无参调用：读取当前 same 所属的父级 same
   * - 传 uri + callback：创建子 same，子 same 的 URI 在当前 same 之上叠加，
   *   生成的子 RouteSame 作为纯容器供其内部叶子路由通过 same 位引用，
   *   resolvedUri() 取子 same 的 uri 即得完整共用 URI。
   *
   * ```
   * Route::same('users', function ($rs) {
   *   $rs->same('{id}', function ($rs2) {
   *     $rs2->get(ShowUserController::class); // uri = users/{id}
   *   });
   * });
   * ```
   *
   * @param string|null $uri 子 same 的 URI（相对当前 same）
   * @param callable|null $callback 子 same 回调（注入子 RouteSame）
   * @return RouteSame|mixed 无参返回父级 same；传值返回新建子 same
   */
  function same($uri = null, $callback = null)
  {
    if ($uri === null && $callback === null) {
      return $this->same;
    }
    // 传相对 URI，由构造在父 same 的 URI 之上 merge，避免重复叠加
    return new RouteSame($uri, $callback, $this, $this->group);
  }

  /**
   * same 内含 group（同 URI 下细分子组）+ 读取所属组（双语义）
   *
   * - 无参调用：读取当前 same 所属的组（交叉嵌套时才有值）
   * - 传 prefix + callback：在 same 内创建子组，子 RouteGroup 通过其 same 位引用
   *   当前 same，resolvedUri() 组合「组前缀 + same 共用 URI + 叶子 URI」。
   *
   * ```
   * Route::same('users', function ($rs) {
   *   $rs->group('admin', function ($g) {
   *     $g->get('list', ListAdminController::class); // uri = admin/users/list
   *   });
   * });
   * ```
   *
   * @param string|null $prefix 子组前缀
   * @param callable|null $callback 子组回调（注入子 RouteGroup）
   * @return RouteGroup|mixed 无参返回所属组；传值返回新建子组
   */
  function group($prefix = null, $callback = null)
  {
    if ($prefix === null && $callback === null) {
      return $this->group;
    }
    return new RouteGroup($prefix, $callback, $this, $this->group);
  }

  /**
   * 共用 URI（读写一体：无参读取，传值写入返回 $this）
   *
   * @param string|null $value
   * @return string|$this
   */
  function uri($value = null)
  {
    if ($value === null) return $this->uri;

    $this->uri = $value;
    return $this;
  }

  /* ==================== 同 URI DSL（只传 controller） ==================== */

  /**
   * 同 URI 叶子 DSL 公共构造：归属当前 same
   *
   * controller 为 null 时不调用 ->controller()，避免吞掉实例（返回 null），
   * 使叶子 controller 留空、由 resolve() 沿祖先链继承。
   */
  private function leaf($method, $controller)
  {
    $r = new RouteRegister("", $method, $this->group, $this);
    if ($controller !== null) {
      $r->controller($controller);
    }
    Routes::push($r);
    return $r;
  }

  function get($controller = null)
  {
    return $this->leaf("get", $controller);
  }
  function post($controller = null)
  {
    return $this->leaf("post", $controller);
  }
  function put($controller = null)
  {
    return $this->leaf("put", $controller);
  }
  function patch($controller = null)
  {
    return $this->leaf("patch", $controller);
  }
  function delete($controller = null)
  {
    return $this->leaf("delete", $controller);
  }
  function head($controller = null)
  {
    return $this->leaf("head", $controller);
  }
  function options($controller = null)
  {
    return $this->leaf("options", $controller);
  }
  function any($controller = null)
  {
    return $this->leaf("*", $controller);
  }

  /**
   * same 内 fallback 兜底路由（该 same 下所有正常路由未命中时命中）
   *
   * 不限定方法/URI；叶子以当前 same 为其 same 位、所属组为 group 位，
   * resolve() 沿祖先链继承 domain 等。
   *
   * @param string|array|\Closure|null $controller 兜底控制器/闭包
   * @return RouteRegister
   */
  function fallback($controller = null)
  {
    $r = new RouteRegister("", "*", $this->group, $this);
    $r->fallback(true);
    if ($controller !== null) {
      $r->controller($controller);
    }
    Routes::push($r);
    return $r;
  }
}
