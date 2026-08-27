<?php

namespace kernel\Foundation\Router;

use kernel\Foundation\HTTP\URL;

/**
 * 单个路由注册器（新 R 路由体系）
 *
 * 由 Route::get / post / put / patch / delete / head / options / any 实例化，
 * 作为「单个路由」的定义载体。Route 静态方法已定型路由性质（单路由/组/同 URI），
 * 本类只负责承载并产出单个路由的完整定义（resolve()）。
 *
 * 支持链式补充：
 *
 * ```
 * Route::get('links', ListLinksController::class)
 *     ->name('links.list')
 *     ->middleware([AuthMiddleware::class])
 *     ->where('id', '[0-9]+');
 * ```
 *
 * 读 / 写一体的 setter（name/prefix/controller/method/uri/parameters）
 * 传值写入返回 $this，无参读取返回当前值。
 */
class RouteRegister
{
  /* ==================== 属性 ==================== */

  /**
   * HTTP 方法（小写），any 用 "*"
   *
   * @var string
   */
  protected $method = "";

  /**
   * 原始 URI（未含组前缀）
   *
   * @var string
   */
  protected $uri = "";

  /**
   * 组前缀（由 RouteGroup 组上下文注入）
   *
   * @var string
   */
  protected $prefix = "";

  /**
   * 路由级中间件（不含组中间件）
   *
   * @var array
   */
  protected $middlewares = [];

  /**
   * 路由名称（用于 url() 生成、redirect() 反查）
   *
   * @var string
   */
  protected $name = "";

  /**
   * 控制器：类名字符串、[类名, 方法名] 数组或闭包
   *
   * @var string|array|\Closure|null
   */
  protected $controller = null;

  /**
   * 控制器实例化参数
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * 路由参数约束（参数名 => 正则）
   *
   * @var array
   */
  protected $where = [];

  /**
   * 额外参数（隐式传值，不在 URL 中，匹配后合并进请求参数）
   *
   * 针对不同路由可设置不同的额外参数对，例如：
   * ```
   * Route::get('blog/:id', 'blog/read')->append(['status' => 1, 'app_id' => 5]);
   * ```
   * status、app_id 不在 URL 中，属于隐式传值，可作安全防护 / 上下文注入。
   *
   * @var array
   */
  protected $append = [];

  /**
   * 所属组（RouteGroup 实例，注册接通时用于回填组上下文）
   *
   * @var RouteGroup|null
   */
  protected $group = null;

  /**
   * 生效域名（由 RouteDomain 域名组注入；"*" 表示对所有域名生效，即不区分域名）
   *
   * @var string
   */
  protected $domain = "*";

  /**
   * 所属同 URI 注册器（RouteSame 实例）
   *
   * @var RouteSame|null
   */
  protected $same = null;

  /**
   * 是否为 fallback 兜底路由（所有正常路由未命中时命中；不限定方法/URI）
   *
   * @var bool
   */
  protected $fallback = false;

  /**
   * 构造
   *
   * @param string $uri 原始 URI
   * @param string $method HTTP 方法（小写，any 用 "*"）
   * @param RouteGroup|null $group 所属组
   * @param RouteSame|null $same 所属同 URI 注册器
   */
  function __construct($uri, $method = "*", $group = null, $same = null)
  {
    $this->uri = $uri;
    $this->method = $method;
    $this->group = $group;
    $this->same = $same;
  }

  /* ==================== 读写一体 setter ==================== */

  /**
   * 设置或读取路由名称
   *
   * @param string|null $value 传值写入，不传仅读取
   * @return $this|string
   */
  function name($value = null)
  {
    if ($value !== null) {
      $this->name = $value;
      return $this;
    }
    return $this->name;
  }

  /**
   * 判断当前路由是否已命名（含沿祖先链继承到的最终名）
   *
   * @return bool
   */
  function hasName()
  {
    return $this->resolvedName() !== "";
  }

  /**
   * 设置或读取组前缀
   *
   * @param string|null $value 传值写入，不传仅读取
   * @return $this|string
   */
  function prefix($value = null)
  {
    if ($value !== null) {
      $this->prefix = $value;
      return $this;
    }
    return $this->prefix;
  }

  /**
   * 设置或读取生效域名（"*" 表示对所有域名生效，即不区分域名）
   *
   * @param string|null $value 传值写入，不传仅读取
   * @return $this|string
   */
  function domain($value = null)
  {
    if ($value !== null) {
      $this->domain = $value;
      return $this;
    }
    return $this->domain;
  }

  /**
   * 设置或读取 fallback 兜底标记（所有正常路由未命中时命中；不限定方法/URI）
   *
   * 传 true/false 写入，不传读取。
   *
   * @param bool|null $value 传值写入，不传仅读取
   * @return $this|bool
   */
  function fallback($value = null)
  {
    if ($value !== null) {
      $this->fallback = (bool) $value;
      return $this;
    }
    return $this->fallback;
  }

  /**
   * 设置或读取控制器
   *
   * @param string|array|\Closure|null $value 传值写入，不传仅读取
   * @return $this|string|array|\Closure|null
   */
  function controller($value = null)
  {
    if ($value !== null) {
      $this->controller = $value;
      return $this;
    }
    return $this->controller;
  }

  /**
   * 设置或读取 HTTP 方法（小写，any 用 "*"）
   *
   * @param string|null $value 传值写入，不传仅读取
   * @return $this|string
   */
  function method($value = null)
  {
    if ($value !== null) {
      $this->method = $value;
      return $this;
    }
    return $this->method;
  }

  /**
   * 设置或读取原始 URI（未含组前缀）
   *
   * @param string|null $value 传值写入，不传仅读取
   * @return $this|string
   */
  function uri($value = null)
  {
    if ($value !== null) {
      $this->uri = $value;
      return $this;
    }
    return $this->uri;
  }

  /**
   * 设置或读取控制器实例化参数
   *
   * @param array|null $value 传值写入，不传仅读取
   * @return $this|array
   */
  function parameters($value = null)
  {
    if ($value !== null) {
      $this->parameters = $value;
      return $this;
    }
    return $this->parameters;
  }

  /* ==================== 中间件 ==================== */

  /**
   * 设置路由级中间件（覆盖，非追加）
   *
   * 可传单个数组，或一个或多个中间件参数。
   *
   * @param array|string|object ...$middlewares 中间件
   * @return $this
   */
  function middleware(...$middlewares)
  {
    $this->middlewares = count($middlewares) === 1 && is_array($middlewares[0])
      ? $middlewares[0]
      : $middlewares;
    return $this;
  }

  /**
   * 读取路由级中间件
   *
   * @return array
   */
  function middlewares()
  {
    return $this->middlewares;
  }

  /* ==================== 参数约束 ==================== */

  /**
   * 设置路由参数约束（合并，参数名 => 正则）
   *
   * ```
   * Route::get('users/{id}', UserController::class)->where('id', '[0-9]+');
   * Route::get('users/{id}', UserController::class)->where(['id' => '[0-9]+']);
   * ```
   *
   * @param string|array $name 参数名或「参数名 => 正则」关联数组
   * @param string|null $regex 当 $name 为字符串时必填的正则
   * @return $this
   */
  function where($name, $regex = null)
  {
    if (is_array($name)) {
      $this->where = array_merge($this->where, $name);
    } else {
      $this->where[$name] = $regex;
    }
    return $this;
  }

  /**
   * 设置数字参数约束（[0-9]+）
   *
   * @param string ...$names 参数名
   * @return $this
   */
  function whereNumber(...$names)
  {
    $map = [];
    foreach ($names as $n) {
      $map[$n] = "[0-9]+";
    }
    return $this->where($map);
  }

  /**
   * 设置字母参数约束（[a-zA-Z]+）
   *
   * @param string ...$names 参数名
   * @return $this
   */
  function whereAlpha(...$names)
  {
    $map = [];
    foreach ($names as $n) {
      $map[$n] = "[a-zA-Z]+";
    }
    return $this->where($map);
  }

  /**
   * 约束指定参数为字母数字（a-zA-Z0-9）
   *
   * @param string ...$names 参数名
   * @return $this
   */
  function whereAlphaNumeric(...$names)
  {
    $map = [];
    foreach ($names as $n) {
      $map[$n] = "[a-zA-Z0-9]+";
    }
    return $this->where($map);
  }

  /**
   * 约束指定参数为给定值之一
   *
   * @param string $name 参数名
   * @param array  $values 允许的值列表
   * @return $this
   */
  function whereIn($name, $values)
  {
    $escaped = array_map(function ($v) {
      return preg_quote((string)$v, "#");
    }, $values);
    return $this->where([$name => "(?:" . implode("|", $escaped) . ")"]);
  }

  /**
   * 约束指定参数为 UUID 格式（8-4-4-4-12）
   *
   * @param string ...$names 参数名
   * @return $this
   */
  function whereUuid(...$names)
  {
    $map = [];
    foreach ($names as $n) {
      $map[$n] = "[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}";
    }
    return $this->where($map);
  }

  /**
   * 读取路由参数约束
   *
   * @return array
   */
  function getWhere()
  {
    return $this->where;
  }

  /**
   * 获取 URI 动态参数（参数名 => 正则）
   *
   * 解析当前 URI 中的动态参数并返回关联数组。支持三种占位形式：
   * - `{param:regex}` → `param => regex`
   * - `{param}`（无内联正则）→ `param => [^/]+`（默认）
   * - `{?param[:regex]}`（可选参数）→ 同必选，正则同上
   *
   * where() 约束（参数名 => 正则）优先于 URI 内联正则。
   *
   * ```
   * Route::get('user/{userId:\w+}')->params(); // ['userId' => '\w+']
   * Route::get('users/{id}/posts/{slug}')
   *     ->where('id', '[0-9]+')
   *     ->params();                         // ['id' => '[0-9]+', 'slug' => '[^/]+']
   * ```
   *
   * @return array<string, string>
   */
  function params()
  {
    //* 全局 pattern 作为默认，自身 where 优先覆盖；解析规则统一委托 Routes::extractParams
    $where = array_merge(Routes::patterns(), $this->where);
    return Routes::extractParams($this->uri(), $where);
  }

  /**
   * 所属组
   *
   * @return RouteGroup|null
   */
  function group()
  {
    return $this->group;
  }

  /**
   * 所属同 URI 注册器
   *
   * @return RouteSame|null
   */
  function same()
  {
    return $this->same;
  }

  /**
   * 额外参数（读写一体：无参读取，传数组写入合并返回 $this）
   *
   * 设置路由跳转时隐式传入操作的参数对（不在 URL 中的参数），
   * 匹配后合并进请求参数，可作安全防护 / 上下文注入。可多次调用合并。
   *
   * ```
   * Route::get('blog/:id', 'blog/read')->append(['status' => 1, 'app_id' => 5]);
   * ```
   *
   * @param array|null $extras 额外参数（参数名 => 值）
   * @return array|$this 无参返回当前额外参数；传值返回 $this
   */
  function append($extras = null)
  {
    if ($extras === null) return $this->append;

    $this->append = array_merge($this->append, $extras);
    return $this;
  }

  /**
   * 产出完整路由定义（供注册接通 / 下游消费）
   *
   * 父级属性沿「same + 组链（组中组）」逐级累积合并：
   * - controller：自身有则优先用，否则从最近父级（same 优先，再沿组链由近及远）取第一个非空
   * - middlewares / parameters / where / append：父在前、自身在后合并
   *   （后者同名键覆盖前者；同 same 视为最近父级，组链从最外层父组逐级向内）
   * - name：组链各父组 name 前缀（外层在前）+ 自身 name 拼接（自身 name 为空则结果为空）
   * - method：自身 method 为空则从最近父级取第一个非空
   * - domain：自身 domain 为空则从最近父级取第一个非空（"*" = 全局生效）
   * - params：基于合并后的完整 URI 与合并后的 where 重新解析
   * - uri：已拼接父 uri / 组前缀的最终 URI（去多余斜杠）
   *
   * @return array{name: string, method: string, uri: string,
   *   controller: mixed, methodName: string|null,
   *   middlewares: array, parameters: array, where: array,
   *   params: array, append: array, domain: string, fallback: bool}
   */
  function resolve()
  {
    // 祖先链（近 → 远，含 same 与 group 两条链的逐级父级）
    $anc = $this->ancestors();

    //* 逐级累积 middlewares / parameters / where / append（最外层父在前，自身最后）
    $middlewares = [];
    $parameters  = [];
    $where       = [];
    $append      = [];
    foreach (array_reverse($anc) as $node) {
      $middlewares = array_merge($middlewares, $node->middlewares());
      $parameters  = array_merge($parameters,  $node->parameters());
      $where       = array_merge($where,       $node->getWhere());
      $append      = array_merge($append,      $node->append());
    }
    $middlewares = array_merge($middlewares, $this->middlewares());
    $parameters  = array_merge($parameters,  $this->parameters());
    $where       = array_merge($where,       $this->getWhere());
    $append      = array_merge($append,      $this->append());

    //* 全局 pattern 作为最弱默认：路由自身/祖先 where 优先覆盖同名
    $where = array_merge(Routes::patterns(), $where);

    $uri = $this->resolvedUri();

    //* name：父级 name 前缀（外层在前）+ 自身 name
    $namePrefix = "";
    foreach (array_reverse($anc) as $node) {
      $namePrefix .= $node->name();
    }
    $childName = $this->name();
    $name = $childName !== "" ? $namePrefix . $childName : $childName;

    //* method：自身优先，否则沿「group 链 → same 链」取第一个非空
    //   （并有时 group 优先）
    $childMethod = $this->method();
    $method = $childMethod !== "" ? $childMethod : $this->inheritedValue("method", $this->inheritedList());

    //* controller：自身优先，否则沿「group 链 → same 链」取第一个非 null
    //   （并有时 group 优先）
    $controller = $this->controller() !== null
      ? $this->controller()
      : $this->inheritedValue("controller", $this->inheritedList());
    $controllerTarget = $this->resolveControllerTarget($controller);

    //* domain：自身优先，否则沿「group 链 → same 链」取第一个非 "*"（"*" = 全局）
    $childDomain = $this->domain();
    if ($childDomain !== "*") {
      $domain = $childDomain;
    } else {
      $domain = "*";
      foreach ($this->inheritedList() as $node) {
        if ($node->domain() !== "*") {
          $domain = $node->domain();
          break;
        }
      }
    }
    //* 注册端归一化：与 Router 消费端一致（小写 + 剥端口 + 去 IPv6 方括号），
    //   保证域名组无论怎么注册（大小写/端口）都能命中归一后的请求 Host
    if ($domain !== "*") {
      $domain = URL::normalizeDomain($domain);
    }

    //* 中间件保持原样（字符串别名或类名），执行时由 Middleware::execute() 按键名查找

    return [
      "name" => $name,
      "method" => $method,
      "uri" => $uri,
      "controller" => $controllerTarget["controller"],
      "methodName" => $controllerTarget["methodName"],
      "middlewares" => $middlewares,
      "parameters" => $parameters,
      "where" => $where,
      "params" => $this->resolveParams($uri, $where),
      "append" => $append,
      "domain" => $domain,
      "fallback" => $this->fallback(),
    ];
  }

  /**
   * 解析控制器目标（契约对齐旧版 resolveControllerTarget）
   *
   * - [类, 方法] 数组 → controller=类名, methodName=方法（缺省 "data"）
   * - 其他（字符串 / 闭包）→ controller=原值, methodName=null
   *
   * @param mixed $controller 原始控制器（类名 / [类, 方法] / 闭包）
   * @return array{controller: mixed, methodName: string|null}
   */
  protected function resolveControllerTarget($controller)
  {
    if (is_array($controller) && isset($controller[0])) {
      return [
        "controller" => $controller[0],
        "methodName" => $controller[1] ?? "data",
      ];
    }
    return [
      "controller" => $controller,
      "methodName" => null,
    ];
  }

  /**
   * 非 URI 继承列表：group 链优先、再 same 链，均近 → 远，去重
   *
   * 与 ancestors()（按嵌套深度排序）不同，这里用于「并有时 group 优先」的
   * 属性继承（method / controller）：先沿 group 指针取全部父组，再沿 same
   * 指针取全部父 same。交叉嵌套时 group 属性恒优先于 same 属性。
   *
   * @return RouteRegister[]
   */
  protected function inheritedList()
  {
    $list = [];
    $seen = [];
    foreach (["group", "same"] as $m) {
      $node = $this;
      while ($node = $node->$m()) {
        $id = spl_object_id($node);
        if (!isset($seen[$id])) {
          $seen[$id] = 1;
          $list[] = $node;
        }
      }
    }
    return $list;
  }

  /**
   * 沿继承列表（近 → 远）取第一个非空（或非 null）的父级属性值
   *
   * @param string $prop 属性取值方法名（method()/controller()）
   * @param RouteRegister[] $anc 继承列表（近 → 远，不含自身）
   * @return mixed 找到返回该值，未找到返回 null
   */
  protected function inheritedValue($prop, $anc)
  {
    foreach ($anc as $node) {
      $value = $node->$prop();
      if ($prop === "controller" ? $value !== null : $value !== "") {
        return $value;
      }
    }
    return null;
  }

  /**
   * 收集祖先链（近 → 远，不含自身），同时沿 same 与 group 两条父级指针逐级向上
   *
   * 交叉嵌套（group 内含 same、same 内含 group）时，一个节点可能同时有
   * same 与 group 两个父级（更外层的通过指针逐级可达）。这里以「到叶子的
   * 嵌套深度」为准对全部祖先排序：越靠外层深度越大，故深度升序即近 → 远。
   * 深度取最大可达值（如 group 内的 same，其 group 既经叶子的 group 位、
   * 也经叶子的 same→RouteSame→group 两条路径可达，深度取较大者）。
   *
   * @return RouteRegister[]
   */
  protected function ancestors()
  {
    $depth = [];
    $nodes = [];
    $this->measureDepth($this, 0, $depth, $nodes);

    $items = [];
    foreach ($depth as $id => $d) {
      $items[] = ["d" => $d, "node" => $nodes[$id]];
    }
    usort($items, function ($a, $b) {
      return $a["d"] - $b["d"];
    }); // 深度升序 = 近→远

    $anc = [];
    foreach ($items as $it) $anc[] = $it["node"];
    return $anc;
  }

  /**
   * 递归测量各祖先相对叶子的嵌套深度（叶子=0，父级深度=子级+1）
   *
   * 同时沿 same 与 group 两条指针扩散；已测得更深则不重复下沉，保证有向
   * 无环图（全部指向上层）下终止。
   *
   * @param RouteRegister $node 当前节点
   * @param int $d 当前节点深度
   * @param array &$depth 对象 id => 最大深度
   * @param array &$nodes 对象 id => 节点实例
   */
  protected function measureDepth($node, $d, &$depth, &$nodes)
  {
    foreach (["same", "group"] as $m) {
      $p = $node->$m();
      if (!$p) continue;
      $id = spl_object_id($p);
      $nodes[$id] = $p;
      if (isset($depth[$id]) && $depth[$id] >= $d + 1) continue;
      $depth[$id] = $d + 1;
      $this->measureDepth($p, $d + 1, $depth, $nodes);
    }
  }

  /**
   * 解析 URI 动态参数（参数名 => 正则）
   *
   * 与 params() 解析规则一致，但基于传入的完整 uri 与合并后的 where：
   * - where 约束优先于 URI 内联正则
   * - 支持 `{param:regex}` / `{param}` / `{?param[:regex]}`
   *
   * @param string $uri 完整 URI
   * @param array  $where 合并后的参数约束
   * @return array<string, string>
   */
  protected function resolveParams($uri, $where)
  {
    //* 与 params() 解析规则一致（统一委托 Routes::extractParams）
    return Routes::extractParams($uri, $where);
  }

  /**
   * 解析最终 URI
   *
   * 遵循「谁先定义谁在前」：沿 same 与 group 两条父级指针收集全部祖先的
   * 相对路径段，按嵌套深度降序（外层先定义 → 在前）排列，最后拼上自身段。
   *
   * - RouteSame 节点贡献 uri 段，RouteGroup 节点贡献 prefix 段（构造时保留相对段，不预合并）
   * - 交叉嵌套（same 内含 group / group 内含 same）时按深度决定先后：
   *   `same('users')->group('admin')` → users/admin（same 外）
   *   `group('admin')->same('users')` → admin/users（group 外）
   * - 根除外去前导斜杠，多余斜杠归一化
   *
   * @return string
   */
  protected function resolvedUri()
  {
    $segments = [];
    // 外层（深度大）在前：ancestors() 为近→远，故 reverse
    foreach (array_reverse($this->ancestors()) as $node) {
      $seg = $this->mergePath($node->prefix(), $node->uri());
      if ($seg !== "") $segments[] = $seg;
    }
    // 自身段（通常 RouteSame/RouteGroup 下叶子无段，纯 group/same 链最内叶子有 uri）
    $own = $this->mergePath($this->prefix(), $this->uri());
    if ($own !== "") $segments[] = $own;

    $uri = implode("/", array_map(function ($s) {
      return trim($s, "/");
    }, $segments));

    return $uri === "/" ? "/" : ltrim($uri, "/");
  }

  /**
   * 计算最终路由名（沿祖先链：各父组 name 前缀外层在前 + 自身 name）
   *
   * 与 resolve() 中 name 的计算一致；自身 name 为空则结果为空。
   *
   * @return string
   */
  protected function resolvedName()
  {
    $namePrefix = "";
    foreach (array_reverse($this->ancestors()) as $node) {
      $namePrefix .= $node->name();
    }
    $childName = $this->name();
    return $childName !== "" ? $namePrefix . $childName : $childName;
  }

  /**
   * 合并父路径与子路径：有则拼、无则只用父
   *
   * @param string $parent 父路径（可为空）
   * @param string $child  子路径（可为空）
   * @return string
   */
  protected function mergePath($parent, $child)
  {
    if ($child === "" || $child === null) {
      return $parent;
    }
    if ($parent === "" || $parent === null) {
      return $child;
    }
    return trim($parent, "/") . "/" . trim($child, "/");
  }
}
