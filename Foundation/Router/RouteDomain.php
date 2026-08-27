<?php

namespace kernel\Foundation\Router;

class RouteDomain extends RouteRegister
{
  function __construct($domain, $callback, $same = null, $group = null)
  {
    $this->domain = $domain;
    $this->same = $same;
    $this->group = $group;
    $callback($this);
  }

  /**
   * 域名组内含子组 + 读取所属组（双语义，兼容父类 getter）
   *
   * - 无参调用：读取当前域名组所属的父组（继承 RouteRegister::group() getter 语义）
   * - 传 prefix + callback：创建子组，子组通过其 group 位引用当前域名组，
   *   resolve() 沿祖先链继承当前域名的 domain 与子组前缀。
   *
   * @param string|null $prefix 子组前缀
   * @param callable|null $callback 子组回调（注入子 RouteGroup）
   * @return RouteGroup|mixed 无参返回所属父组；传值返回新建子组
   */
  function group($prefix = null, $callback = null)
  {
    if ($prefix === null && $callback === null) {
      return $this->group;
    }
    return new RouteGroup($prefix, $callback, $this->same, $this);
  }

  /**
   * 域名组内含 same + 读取所属 same（双语义）
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
   * 域名组内叶子 DSL 公共构造：生成归属当前域名组的路由载体
   *
   * 叶子以当前域名组为其 group 位，resolve() 沿祖先链继承 domain。
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
   * 域名组内 fallback 兜底路由（仅该域名及回退全局未命中时命中）
   *
   * 叶子以当前域名组为其 group 位，resolve() 沿祖先链继承 domain；不限定
   * 方法/URI，仅标记 fallback。
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
