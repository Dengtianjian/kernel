<?php

namespace kernel\Foundation\Router;

/**
 * 路由表集合（静态容器）
 *
 * 静态持有全部已注册的 RouteRegister（$routes 实例表）。push / remove 仅维护
 * 实例表；分发的三表（$staticRoutes / $paramRoutes / $named）由 distribute() 在
 * 读取时统一重建。
 *
 * 承载全部路由表的分发与匹配（以静态属性替代已被移除的 RouteCollection 实例）。
 */
class Routes
{
  /** 原始注册器表（push 顺序，数值键） */
  static protected $routes = [];

  /** 静态路由定义表：domain => method => uri => 定义（无动态参数，domain 用 "*" 表示全局） */
  static protected $staticRoutes = [];

  /** 动态路由定义表：domain => method => uri => 定义（含 {} 参数，domain 用 "*" 表示全局） */
  static protected $paramRoutes = [];

  /** 命名路由索引：路由名 => 路由定义（无名称的路由不入表） */
  static protected $named = [];

  /** fallback 兜底路由表：domain => 路由定义（每域名一个；不入 static/param 表） */
  static protected $fallback = [];

  /** 全局路由参数格式约束：参数名 => 正则（一次定义全局生效，路由自身 where 优先覆盖） */
  static protected $patterns = [];

  /** 分表脏标记：实例表/全局约束变更后置真，distribute() 仅在其为真时全量重建 */
  static protected $dirty = false;

  /**
   * URI 动态参数占位符正则（唯一来源）
   *
   * 同时被「解析参数映射」（extractParams）与「编译匹配正则」（compilePattern）
   * 使用，保证占位符语法解析逻辑全局唯一、不漂移。
   *
   * 捕获组：1=可选标记（?，可空），2=参数名（\w+），3=内联正则（可空）。
   */
  const PLACEHOLDER = '/\{(\??)(\w+)(?::([^}]+))?\}/';

  /**
   * 编译带动态占位符的 URI 为完整匹配正则
   *
   * 将 `{param}` / `{param:regex}` / `{?param[:regex]}` 替换为命名捕获组正则，
   * 以 "#^...$#" 包裹返回。$params 为「参数名 => 正则」约束（where / 全局 pattern
   * 合并结果），同名优先于内联正则；$params 缺省键时回退内联正则或默认 [^/]+。
   *
   * @param string $uri 含占位符的 URI
   * @param array  $params 参数名 => 正则 约束映射
   * @return string 以 "#^" + 编译体 + "$#" 包裹的正则
   */
  static function compilePattern($uri, $params)
  {
    $compiled = preg_replace_callback(self::PLACEHOLDER, function ($m) use ($params) {
      $optional = $m[1] === "?" ? "?" : "";
      $name = $m[2];
      $regex = $params[$name] ?? ($m[3] ?? "[^/]+");
      return "(?P<" . $name . ">" . $regex . ")" . $optional;
    }, $uri);
    return "#^" . $compiled . "$#";
  }

  /**
   * 解析 URI 动态参数映射（参数名 => 正则）
   *
   * 与 compilePattern 共用 PLACEHOLDER，解析规则一致：where 约束优先于 URI 内联
   * 正则，缺省回退 [^/]+。支持 `{param:regex}` / `{param}` / `{?param[:regex]}`。
   *
   * @param string $uri 含占位符的 URI
   * @param array  $where 参数约束（where / 全局 pattern 合并）
   * @return array<string, string>
   */
  static function extractParams($uri, $where)
  {
    $params = [];
    preg_match_all(self::PLACEHOLDER, $uri, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
      $params[$m[2]] = isset($where[$m[2]]) ? $where[$m[2]] : ($m[3] ?? "[^/]+");
    }
    return $params;
  }

  /**
   * 域名查找序：指定域名优先，未命中回退全局（"*"）
   *
   * 统一供 match / matchOptions / matchExplicit 使用，保证三处域名回退语义一致。
   *
   * @param string|null $domain 请求域名（null 视为全局）
   * @return string[] 按优先级排序的域名列表
   */
  static protected function domains($domain)
  {
    $domain = $domain === null ? "*" : $domain;
    return $domain !== "*" ? [$domain, "*"] : ["*"];
  }

  /**
   * 追加路由到路由表（仅维护实例表，不重建分表）
   *
   * 分表由 distribute() 在读取时统一重建（脏标记触发）。
   *
   * @param RouteRegister $RR
   * @return void
   */
  static function push(RouteRegister $RR)
  {
    self::$routes[] = $RR;
    self::$dirty = true;
  }

  /**
   * 从路由表移除指定路由（仅维护实例表，不重建分表）
   *
   * 按实例严格匹配移除；分表由 distribute() 在读取时统一重建（脏标记触发）。
   *
   * @param RouteRegister $RR
   * @return void
   */
  static function remove(RouteRegister $RR)
  {
    self::$routes = array_values(array_filter(self::$routes, function ($item) use ($RR) {
      return $item !== $RR;
    }));
    self::$dirty = true;
  }

  /**
   * 清空路由表与分表
   *
   * @return void
   */
  static function clear()
  {
    self::$routes = [];
    self::$staticRoutes = [];
    self::$paramRoutes = [];
    self::$named = [];
    self::$fallback = [];
    self::$patterns = [];
    self::$dirty = false;
  }

  /**
   * 定义全局路由参数格式约束（一次定义，对后续所有路由生效）
   *
   * 与路由自身 where 同名时，路由 where 优先覆盖全局约束；可作为 URI 内联
   * 正则（`{id:...}`）的全局默认。支持单条 `pattern('id', '[0-9]+')` 或
   * 批量 `pattern(['id' => '[0-9]+', 'slug' => '[a-z-]+'])`。
   *
   * @param string|array $name 参数名（字符串）或 参数名=>正则 映射（数组）
   * @param string|null $regex 当 $name 为字符串时必填的正则
   * @return void
   */
  static function pattern($name, $regex = null)
  {
    if (is_array($name)) {
      self::$patterns = array_merge(self::$patterns, $name);
      self::$dirty = true;
      return;
    }
    //* 强制必填 regex：避免存入 null 导致语义含糊
    if ($regex === null) {
      throw new \InvalidArgumentException("pattern() 必须提供正则表达式，参数 \"{$name}\" 缺少 regex。");
    }
    self::$patterns[$name] = $regex;
    self::$dirty = true;
  }

  /**
   * 读取全部全局参数格式约束
   *
   * @return array<string, string>
   */
  static function patterns()
  {
    return self::$patterns;
  }

  /**
   * 分发：遍历全部注册器，逐条 resolve 重建分表与命名索引
   *
   * 读取（match / tables / find）前调用。仅当 $dirty 为真（实例表/全局约束
   * 变更过）才全量重建，否则直接复用缓存分表，避免每请求重复 O(n) 解析。
   *
   * @return void
   */
  static protected function distribute()
  {
    if (!self::$dirty) {
      return;
    }
    self::$staticRoutes = [];
    self::$paramRoutes = [];
    self::$named = [];
    self::$fallback = [];
    $seen = []; // 重复注册检测：domain+method+uri => 行号
    foreach (self::$routes as $RR) {
      $def = $RR->resolve();
      $domain = $def["domain"] ?? "*";
      //* fallback：不限定方法/URI，单独按域名入兜底表，不入 static/param
      //* 命名 fallback 不入 $named：url() 对 fallback 名返回 "/" 语义边缘，禁止反向生成
      if (!empty($def["fallback"])) {
        self::$fallback[$domain] = $def;
        continue;
      }
      $type = count($def["params"]) ? "paramRoutes" : "staticRoutes";
      $key = "{$domain}:{$def['method']}:{$def['uri']}";
      if (isset($seen[$key])) {
        trigger_error("路由重复注册：{$key}，后者覆盖前者", E_USER_WARNING);
      }
      $seen[$key] = true;
      self::${$type}[$domain][$def["method"]][$def["uri"]] = $def;
      if ($def["name"] !== "") {
        if (isset(self::$named[$def["name"]])) {
          trigger_error("路由名重复：{$def['name']}，后者覆盖前者", E_USER_WARNING);
        }
        self::$named[$def["name"]] = $def;
      }
    }
    //* 动态路由按「段数从多到少」排序：重叠正则（如 posts/{id} 与 posts/{id}/x）
    //* 优先命中更具体的（段更多 / 更长的 URI），而非注册顺序。
    //* 同时预编译正则并固化进 def["compiledPattern"]，避免 matchParam 每次重复编译。
    foreach (self::$paramRoutes as &$methods) {
      foreach ($methods as &$defs) {
        uasort($defs, function ($a, $b) {
          $la = substr_count($a["uri"], "/");
          $lb = substr_count($b["uri"], "/");
          if ($la !== $lb) {
            return $lb <=> $la;
          }
          return strlen($b["uri"]) <=> strlen($a["uri"]);
        });
        foreach ($defs as &$def) {
          $def["compiledPattern"] = self::compilePattern($def["uri"], $def["params"]);
        }
      }
    }
    unset($methods, $defs, $def);
    self::$dirty = false;
  }

  /**
   * 导出完整路由定义表（静态 + 动态，每项为 resolve() 产物）
   *
   * 结构：domain => method => uri => 路由定义（三层索引）。domain 键为路由生效
   * 域名（全局路由为 "*"），method 键照注册方法名（get/post/...），any 用 "*"。
   * 同 uri 下 get 与 post、以及不同域名下同 uri 可各自独立定义。
   * 每项含：name / method / uri（已拼组前缀）/ controller /
   * middlewares / parameters / where / params（动态参数正则）/ append / domain。
   *
   * @return array[] domain => method => uri => 路由定义
   */
  static function tables()
  {
    self::distribute();
    $tables = self::$staticRoutes;
    foreach (self::$paramRoutes as $domain => $methods) {
      foreach ($methods as $method => $uris) {
        $tables[$domain][$method] = array_merge($tables[$domain][$method] ?? [], $uris);
      }
    }
    ksort($tables);
    return $tables;
  }

  /**
   * 匹配指定 method + uri（+ 可选域名）的路由定义
   *
   * 先 distribute() 重建分表，再匹配。域名匹配：先查指定域名，未命中回退全局
   * （domain "*"）路由。匹配序：静态精确（method 优先，any 兜底）→ 动态逐条
   * preg_match。
   * 动态路由（含 {} 参数）按 def["params"]（参数名=>正则）编译正则匹配，
   * 提取命名组作为 def["params"] 匹配值回填；any 动态路由同样参与兜底。
   * 所有正常路由未命中时，按域名序查 fallback 兜底路由（若注册），仍无则返回 null。
   *
   * 注：OPTIONS 预检由 App 层统一处理（fireShutdown 直接结束），此处不做 options
   * 专属汇总；显式注册的 options 路由仍按普通方法参与正常匹配。
   *
   * @param string $method 请求方法（如 get/post），any 路由键为 "*"
   * @param string $uri 请求路径（不含组前缀，精确匹配）
   * @param string|null $domain 当前请求域名；传空/不传则仅查全局（"*"）路由
   * @return array|null 命中返回路由定义（动态路由含匹配到的 params 值），未命中返回 null
   */
  static function match($method, $uri, $domain = null)
  {
    self::distribute();
    $method = strtolower($method);
    $domain = $domain === null ? "*" : $domain;

    //* HEAD 语义等同 GET（仅不含响应体）：未注册 head 路由时回退 get 路由
    $methods = $method === "head" ? ["head", "get"] : [$method];

    // 域名查找序：指定域名优先，回退全局（"*"）
    foreach (self::domains($domain) as $d) {
      $static = self::$staticRoutes[$d] ?? [];
      $param  = self::$paramRoutes[$d] ?? [];

      foreach ($methods as $m) {
        //* 1. 静态精确：方法优先，any 兜底
        if (isset($static[$m][$uri])) {
          return $static[$m][$uri];
        }
        //* 2. 动态：方法表逐条 preg_match
        foreach ($param[$m] ?? [] as $def) {
          $route = self::matchParam($def, $uri);
          if ($route !== null) {
            return $route;
          }
        }
      }
      //* any（"*"）兜底：静态优先，动态逐条
      if (isset($static["*"][$uri])) {
        return $static["*"][$uri];
      }
      foreach ($param["*"] ?? [] as $def) {
        $route = self::matchParam($def, $uri);
        if ($route !== null) {
          return $route;
        }
      }
    }

    //* fallback 兜底：指定域名优先，回退全局（"*"）
    foreach (self::domains($domain) as $d) {
      if (isset(self::$fallback[$d])) {
        $def = self::$fallback[$d];
        //* 兜底路由不限定 URI，匹配时把原始请求路径注入 params，供兜底控制器透传
        if (empty($def["params"])) {
          $def["params"] = ["path" => $uri];
        }
        return $def;
      }
    }

    return null;
  }

  /**
   * 编译动态路由定义并匹配 URI
   *
   * @param array $def 路由定义（含 uri / params）
   * @param string $uri 请求路径
   * @return array|null 命中返回带匹配参数值的定义，未命中返回 null
   */
  static protected function matchParam($def, $uri)
  {
    //* 使用 distribute() 时预编译并固化的正则，避免每次匹配重复编译
    $pattern = $def["compiledPattern"] ?? self::compilePattern($def["uri"], $def["params"]);

    // 编译失败（无效正则）时 preg_match 返回 false，区别于未命中（0），应显式报错而非静默忽略
    $result = @preg_match($pattern, $uri, $matches);
    if ($result === false) {
      throw new \InvalidArgumentException(
        "路由正则编译失败，无法匹配：URI \"{$def["uri"]}\"（当前路径 \"{$uri}\"）。"
        . "请检查 where() 或 URI 内联的正则是否合法。"
      );
    }
    if (!$result) {
      return null;
    }
    $params = [];
    foreach ($matches as $key => $value) {
      if (is_string($key)) {
        $params[$key] = $value;
      }
    }
    $def["params"] = $params;
    return $def;
  }

  /**
   * 按路由名读取路由定义
   *
   * 先 distribute() 重建分表与命名索引，再按名读取。
   *
   * @param string $name 路由名
   * @return array|null 命中返回路由定义，未命中返回 null
   */
  static function find($name)
  {
    self::distribute();
    return self::$named[$name] ?? null;
  }

  /**
   * 按路由名反向生成 URL
   *
   * 先 distribute() 重建命名索引，按名取出路由定义后：
   * - 用 $params 中同名值替换 URI 占位符 `{name}` / `{name:regex}`；必选参数缺失抛异常，
   *   可选参数 `{?name}` 缺省则留空（多余空段自动归一化）
   * - 路径参数值经 rawurlencode 编码（避免 `/ ? # 空格` 破坏 URL）
   * - 未在 URI 中消费的 $params 键值拼成查询串 `?k=v&...`（值经 urlencode）
   * - 传 $domain 时生成绝对 URL（scheme://host/path），否则返回相对路径
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
    self::distribute();
    if (!isset(self::$named[$name])) {
      throw new \InvalidArgumentException("未找到名为 \"{$name}\" 的路由，无法生成 URL。");
    }
    $def = self::$named[$name];
    $used = [];
    $uri = preg_replace_callback(self::PLACEHOLDER, function ($m) use ($name, $params, &$used) {
      $pname = $m[2];
      $optional = $m[1] === "?";
      if (array_key_exists($pname, $params)) {
        $used[$pname] = 1;
        //* 路径参数值编码：避免 / ? # 空格等特殊字符破坏 URL 结构
        return rawurlencode((string)$params[$pname]);
      }
      if ($optional) {
        return ""; // 可选参数缺省：留空段，随后归一化
      }
      throw new \InvalidArgumentException(
        "生成路由 \"{$name}\" 的 URL 缺少必选参数 \"{$pname}\"。"
      );
    }, $def["uri"]);
    // 可选参数留空可能产生多余斜杠，归一化
    $uri = preg_replace("#/{2,}#", "/", $uri);
    $uri = rtrim($uri, "/");
    if ($uri === "") {
      $uri = "/";
    }
    $query = http_build_query(array_diff_key($params, $used));
    $path = $uri . ($query !== "" ? "?" . $query : "");

    //* 绝对 URL：拼 scheme://host/path
    if ($domain !== null) {
      $scheme = $https ? "https" : "http";
      return "{$scheme}://{$domain}/{$path}";
    }
    return $path;
  }
}
