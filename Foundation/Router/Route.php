<?php

namespace kernel\Foundation\Router;

/**
 * 路由定义门面（Route 类）
 *
 * 静态入口，用于以链式/声明式方式定义路由。所有方法返回一个路由注册器实例
 * （RouteRegister / RouteGroup / RouteSame / RouteDomain），可供调用方继续链式
 * 设置（controller / name / middleware / where / append 等），或在 Routes 注册
 * 阶段 push 收集。
 *
 * 四类返回：
 * - `RouteRegister`：普通路由（Route::get/post/.../any 直接定义）
 * - `RouteGroup`（路由组）：Route::group，组前缀 + 内部叶子路由
 * - `RouteSame`（同 URI 注册器）：Route::same，共用 URI 下细分 HTTP 方法的控制器
 * - `RouteDomain`（域名组）：Route::domain，按生效域名声明路由
 *
 * 完整路径与属性合并遵循「谁先定义谁在前」（外层在前）、并有时 group 优先，
 * 由 RouteRegister::resolve() / resolvedUri() 统一处理。
 */
class Route
{
  /**
   * 构造普通路由注册器（门面各 DSL 的公共实现）
   *
   * controller 为 null 时不调用 ->controller()，避免读写一体 setter 把 null
   * 当读取、吞掉实例返回 null（Bug 3）；省略时叶子 controller 留空，
   * 由 resolve() 沿祖先链（group → same）继承。
   *
   * @param string $uri 路由 URI（相对路径段，最终拼接由 resolvedUri 完成）
   * @param string $method HTTP 方法（get/post/.../any，any 作兜底）
   * @param string|array|\Closure|null $controller 控制器/闭包，传则写入
   * @return RouteRegister
   */
  private static function make($uri, $method, $controller)
  {
    $r = new RouteRegister($uri, $method);
    // push 只登记实例表并置脏，分表在读取时（match/tables/find）由 distribute()
    // 惰性重建，因此 controller 就位与否不会影响最终结果。这里仍先写 controller
    // 再 push，保持控制器尽早登记在注册器上。
    if ($controller !== null) {
      $r->controller($controller);
    }
    Routes::push($r);
    return $r;
  }

  /**
   * 注册 GET 路由
   *
   * @param string $uri 路由 URI，如 "users"、"blog/{id:\d+}"
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function get($uri, $controller = null)
  {
    return self::make($uri, "get", $controller);
  }

  /**
   * 注册 POST 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function post($uri, $controller = null)
  {
    return self::make($uri, "post", $controller);
  }

  /**
   * 注册 PUT 路由（整体更新）
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function put($uri, $controller = null)
  {
    return self::make($uri, "put", $controller);
  }

  /**
   * 注册 PATCH 路由（部分更新）
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function patch($uri, $controller = null)
  {
    return self::make($uri, "patch", $controller);
  }

  /**
   * 注册 DELETE 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function delete($uri, $controller = null)
  {
    return self::make($uri, "delete", $controller);
  }

  /**
   * 注册 HEAD 路由（仅返回响应头，无响应体）
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function head($uri, $controller = null)
  {
    return self::make($uri, "head", $controller);
  }

  /**
   * 注册 OPTIONS 路由（预检/跨域探测）
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function options($uri, $controller = null)
  {
    return self::make($uri, "options", $controller);
  }

  /**
   * 注册 ANY 路由（兜底：任意 HTTP 方法均可命中，含静态与动态）
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure|null $controller 控制器类/`[类,方法]`/闭包，可省略
   * @return RouteRegister
   */
  static function any($uri, $controller = null)
  {
    return self::make($uri, "*", $controller);
  }

  /**
   * 注册路由组（组前缀 + 内部叶子路由）
   *
   * 回调内通过注入的 RouteGroup 实例定义叶子路由，组前缀在最终 URI 最前拼接。
   * 组级 name/middleware/where/controller 等可在 RouteGroup 上链式设置，并由叶子
   * 路由经 resolve() 继承。
   *
   * ```
   * Route::group("api", function ($g) {
   *   $g->get("users", UserController::class);   // uri = api/users
   *   $g->group("v1", function ($g2) {           // 组中组
   *     $g2->get("posts", PostController::class); // uri = api/v1/posts
   *   });
   * });
   * ```
   *
   * @param string $prefix 组前缀（相对路径段）
   * @param callable $callback 组回调，注入当前组实例 RouteGroup
   * @return RouteGroup
   */
  static function group($prefix, $callback)
  {
    return new RouteGroup($prefix, $callback);
  }

  /**
   * 注册同 URI 注册器（共用 URI 下细分 HTTP 方法）
   *
   * 回调内通过注入的 RouteSame 实例为同一 URI 定义不同方法的控制器，叶子 URI 由
   * resolvedUri() 沿祖先链拼接得到。
   *
   * ```
   * Route::same("links/{id}", function ($rs) {
   *   $rs->get(ShowLinkController::class);    // GET  /links/{id}
   *   $rs->put(UpdateLinkController::class);  // PUT  /links/{id}
   *   $rs->delete(RemoveLinkController::class);// DELETE /links/{id}
   * });
   * ```
   *
   * @param string $uri 共用 URI（相对路径段）
   * @param callable $callback 回调，注入当前同 URI 实例 RouteSame
   * @return RouteSame
   */
  static function same($uri, $callback)
  {
    return new RouteSame($uri, $callback);
  }

  /**
   * 注册 fallback 兜底路由（所有正常路由未命中时命中）
   *
   * 不限定 URI 与 HTTP 方法，作为任意请求的最终兜底（可用于返回 404 页面、
   * 统一异常响应等）。支持链式设置 controller / middleware / where 等。
   *
   * 默认定义全局兜底；传 $domain 则仅对该域名（及回退全局）生效。
   * 也可放在 Route::domain 组内（经 $d->fallback），语义相同。
   *
   * ```
   * Route::fallback(NotFoundController::class);
   * Route::fallback(ApiNotFoundController::class, "api.example.com"); // 仅 api 域名兜底
   * Route::domain("api.example.com", function ($d) {
   *   $d->fallback(ApiNotFoundController::class);                     // 等价写法
   * });
   * ```
   *
   * @param string|array|\Closure|null $controller 兜底控制器/闭包
   * @param string|null $domain 生效域名（null = 全局兜底）
   * @return RouteRegister
   */
  static function fallback($controller = null, $domain = null)
  {
    $r = new RouteRegister("", "*");
    $r->fallback(true);
    if ($domain !== null) {
      $r->domain($domain);
    }
    if ($controller !== null) {
      $r->controller($controller);
    }
    Routes::push($r);
    return $r;
  }

  /**
   * 定义全局路由参数格式约束（一次定义，对后续所有路由生效）
   *
   * 与路由自身 where 同名时，路由 where 优先覆盖；可作为 URI 内联正则的
   * 全局默认。支持单条或批量：
   *
   * ```
   * Route::pattern('id', '[0-9]+');
   * Route::pattern(['id' => '[0-9]+', 'slug' => '[a-z-]+']);
   * Route::get('posts/{id}/edit', EditController::class);  // id 全局约束为数字
   * ```
   *
   * @param string|array $name 参数名（字符串）或 参数名=>正则 映射（数组）
   * @param string|null $regex 当 $name 为字符串时必填的正则
   * @return void
   */
  static function pattern($name, $regex = null)
  {
    Routes::pattern($name, $regex);
  }

  /**
   * 注册域名组（按生效域名声明路由）
   *
   * 回调内通过注入的 RouteDomain 实例定义叶子路由，路由仅在指定域名下命中；
   * 同 URI 在不同域名组可命中不同控制器。未在域名组内的路由对所有域名生效。
   * 域名组内可嵌套 group / same，域名沿祖先链被子路由继承。
   *
   * ```
   * Route::domain("api.example.com", function ($d) {
   *   $d->get("users", ApiUserController::class);     // 仅 api 域名
   * });
   * Route::get("users", WebUserController::class);    // 其他域名
   * ```
   *
   * @param string $domain 生效域名（如 "api.example.com"，不含协议/端口）
   * @param callable $callback 回调，注入当前域名组实例 RouteDomain
   * @return RouteDomain
   */
  static function domain($domain, $callback)
  {
    return new RouteDomain($domain, $callback);
  }

  /**
   * 按路由名反向生成 URL
   *
   * 见 Routes::url() 的完整说明。URI 占位符由 $params 同名值填充（值经 rawurlencode），
   * 多余项追加为查询串；必选参数缺失抛 InvalidArgumentException。
   * 传 $domain 时生成绝对 URL（scheme://host/path）。
   *
   * ```
   * Route::get('users/{id}/posts', PostController::class)->name('user.posts');
   * Route::url('user.posts', ['id' => 42]);                          // users/42/posts
   * Route::url('user.posts', ['id' => 42, 'page' => 2]);             // users/42/posts?page=2
   * Route::url('user.posts', ['id' => 42], 'api.example.com');       // http://api.example.com/users/42/posts
   * Route::url('user.posts', ['id' => 42], 'api.example.com', true); // https://api.example.com/users/42/posts
   * ```
   *
   * @param string      $name   路由名
   * @param array       $params 路由参数（参数名 => 值）；多出项作为查询串追加
   * @param string|null $domain 域名（非 null 时生成绝对 URL）
   * @param bool        $https  是否 HTTPS（默认 false = http）
   * @return string URL（相对路径或绝对 URL）
   */
  static function url($name, $params = [], $domain = null, $https = false)
  {
    return Routes::url($name, $params, $domain, $https);
  }

  /**
   * 按路由名生成 302 重定向响应
   *
   * 内部调用 url() 生成目标地址，返回 Result 对象（status=302）。
   * 适用于控制器中按命名路由跳转，避免硬编码 URL。
   *
   * ```
   * Route::get('login', LoginController::class)->name('login');
   * return Route::redirect('login');                    // 302 到 /login
   * return Route::redirect('user.posts', ['id' => 42]); // 302 到 /users/42/posts
   * ```
   *
   * @param string $name   路由名
   * @param array  $params 路由参数
   * @return \kernel\Foundation\Result 302 重定向响应
   */
  static function redirect($name, $params = [])
  {
    $url = self::url($name, $params);
    return \kernel\Foundation\Result::failed(null, "重定向", 302)->header("Location", $url);
  }
}
