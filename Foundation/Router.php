<?php

namespace kernel\Foundation;

use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\HTTP\URL;

/**
 * 路由注册与匹配
 *
 * 构造时按模式加载 Routes 文件：http 模式注册 URI 路由、command 模式注册 CLI 命令。
 * 维护全局路由表，提供链式 DSL 风格的 API。
 *
 * ## 使用方式
 * ```
 * // 基本路由
 * Router::get('links', ListLinksController::class);
 * Router::post('links', PostLinkController::class);
 *
 * // 动态路由（{paramName:regex}）
 * Router::get('links/{linkId:\w+}', GetLinkController::class);
 *
 * // same() — 同一 URI 不同方法
 * Router::same('links/{linkId:\w+}', function () {
 *   Router::get(GetLinkController::class);
 *   Router::put(PutLinkController::class);
 * });
 *
 * // group() — 路由组，共享前缀和中间件
 * Router::group('notifications', function () {
 *   Router::get('', ListNoticeController::class);
 *   Router::post('send', SendNoticeController::class);
 * }, [AuthMiddleware::class]);
 * ```
 *
 * ## 匹配优先级
 * 1. 静态路由 `$staticRoutes['common'][$method][$uri]`
 * 2. 静态 `async` 路由
 * 3. 静态 `any` 路由
 * 4. 动态路由逐条 `preg_match`
 */
class Router
{
  /**
   * 静态路由表
   *
   * 结构：`$staticRoutes[$type][$method][$uri] = route`
   *
   * @var array{common: array, async: array, any: array}
   */
  private static $staticRoutes = [];

  /**
   * 动态路由表（含正则参数的 URI）
   *
   * 结构：`$paramsRoutes[$type][$method][$pattern] = route`
   *
   * @var array{common: array, async: array, any: array}
   */
  private static $paramsRoutes = [];

  /** 是否处于 group() 回调中 */
  private static $inGroup = false;

  /** group() 上下文中的共享中间件 */
  private static $groupMiddlewares = [];

  /**
   * 路由前缀栈
   *
   * 由 group() 或 prefix() 设置，register() 中拼接到 URI 前。
   *
   * @var string[]
   */
  private static $prefix = [];

  /**
   * same() 上下文中的共用 URI
   *
   * same() 回调中注册路由时，register() 从此取值。
   *
   * @var string|null
   */
  private static $sameUri = null;

  /**
   * 运行模式：http / command
   *
   * 构造时按模式加载路由：http 模式注册 URI 路由（Router::get 等），
   * command 模式注册 CLI 命令（Router::command）。
   *
   * @var string|null
   */
  private static $mode = null;

  /**
   * CLI 命令表
   *
   * 结构：`$commands[$name] = ["controller" => .., "handleMethodName" => .., "description" => ..]`
   *
   * @var array
   */
  private static $commands = [];

  /**
   * 构造：设置运行模式并加载路由
   *
   * @param string|bool|null $mode 运行模式：http/command；true 视为 http、false 视为 command；
   *                                null 自动判断（非 cli 环境为 http，否则 command）
   */
  public function __construct($mode = null)
  {
    if ($mode === null) {
      if (self::$mode === null) {
        self::$mode = PHP_SAPI !== "cli" ? "http" : "command";
      }
    } else {
      self::$mode = ($mode === true || $mode === "http") ? "http" : "command";
    }

    $this->loadRoutes();
  }

  /**
   * 设置运行模式
   *
   * 特殊场景（如 CLI 下模拟 HTTP 请求）可显式覆盖自动判断的模式。
   *
   * @param string|bool $mode http/command；true 视为 http、false 视为 command
   * @return string 类名（供链式调用）
   */
  public static function setMode($mode)
  {
    self::$mode = ($mode === true || $mode === "http") ? "http" : "command";
    return self::class;
  }

  /**
   * 获取运行模式
   *
   * @return string|null
   */
  public static function mode()
  {
    return self::$mode;
  }

  /**
   * 加载路由文件
   *
   * 扫描 kernel/Routes 与 App/Routes 下所有 PHP 文件并 include_once，
   * 文件内通过 Router::get/command 等注册 URI 路由与 CLI 命令。
   * 收集按运行模式：http 模式仅收集 URI 路由（register），command 模式仅收集 CLI 命令（command），
   * 另一模式的数据不存入类。
   *
   * @return bool
   */
  protected function loadRoutes()
  {
    $localRouteFiles = [];
    $kernelRoutesDir = FileHelper::combinedFilePath(Path::kernelRoot(), "Routes");
    if (is_dir($kernelRoutesDir)) {
      //* 载入kernel路由
      $kernelRouteFiles = FileHelper::recursionScanDir($kernelRoutesDir, null, true);
      if (count($kernelRouteFiles)) {
        $localRouteFiles = array_merge($localRouteFiles, $kernelRouteFiles);
      }
    }

    $appRoutesDir = FileHelper::combinedFilePath(Path::root(), "Routes");
    if (is_dir($appRoutesDir)) {
      //* 载入App的路由
      $appRouteFiles = FileHelper::recursionScanDir($appRoutesDir, null, true);
      if (count($appRouteFiles)) {
        $localRouteFiles = array_merge($localRouteFiles, $appRouteFiles);
      }
    }
    foreach ($localRouteFiles as $fileItem) {
      if (!is_dir($fileItem)) {
        include_once($fileItem);
        self::prefix(null);
      }
    }

    return true;
  }

  /**
   * 设置路由前缀
   *
   * @param string|array|null $prefix 前缀；null 清空
   * @param bool $append 是否追加到现有前缀
   * @return string 类名（供链式调用）
   */
  public static function prefix($prefix, $append = false)
  {
    if ($prefix === null) {
      self::$prefix = [];
      return self::class;
    }
    $prefixes = is_array($prefix) ? $prefix : explode("/", trim($prefix, "/"));
    if ($append) {
      self::$prefix = array_merge(self::$prefix, $prefixes);
    } else {
      self::$prefix = $prefixes;
    }
    return self::class;
  }

  /**
   * 路由组：共享前缀与中间件
   *
   * @param string|array $prefix 组前缀
   * @param \Closure $callback 组内注册回调
   * @param array $middlewares 组中间件
   * @return string 类名（供链式调用）
   */
  public static function group($prefix, \Closure $callback, $middlewares = [])
  {
    $previousPrefix = self::$prefix;
    $previousGroupMiddlewares = self::$groupMiddlewares;
    $previousInGroup = self::$inGroup;

    self::$inGroup = true;
    if ($prefix) {
      self::$prefix = array_merge(self::$prefix, (array)$prefix);
    }
    if (count($middlewares)) {
      self::$groupMiddlewares = array_merge(self::$groupMiddlewares, $middlewares);
    }

    $callback();

    self::$inGroup = $previousInGroup;
    self::$prefix = $previousPrefix;
    self::$groupMiddlewares = $previousGroupMiddlewares;

    return self::class;
  }

  /**
   * 同一 URI 注册不同方法
   *
   * 回调内的 get/post 等方法不再传 URI，仅传控制器。
   *
   * @param string $uri 共用 URI
   * @param \Closure $callback 注册回调
   * @return string 类名（供链式调用）
   */
  public static function same($uri, \Closure $callback)
  {
    $previousSameUri = self::$sameUri;
    self::$sameUri = $uri;
    $callback();
    self::$sameUri = $previousSameUri;
    return self::class;
  }

  /**
   * 注册路由（核心）
   *
   * @param string $type 类型：common / async / any
   * @param string $method HTTP 方法（小写）
   * @param string $uri 路由 URI（不含前导斜杠，根 "/" 除外）
   * @param string|array|\Closure $controller 控制器类名、[类名, 方法名] 或闭包
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function register($type, $method, $uri, $controller, $middlewares = [], $controllerInstantiateParams = [])
  {
    //* 按模式收集：command 模式下不注册 URI 路由（loadRoutes 按模式加载，避免冗余存储）
    if (self::$mode === "command") {
      return self::class;
    }

    //* same() 上下文：URI 由 same() 提供
    if (self::$sameUri !== null) {
      $uri = self::$sameUri;
    }
    //* 前缀拼接
    if (count(self::$prefix)) {
      $uri = trim(implode("/", self::$prefix), "/") . "/" . trim($uri, "/");
    }
    //* 非根 URI 去掉前导斜杠（仅根 "/" 特判）
    if ($uri !== "/") {
      $uri = ltrim($uri, "/");
    }

    $target = self::resolveControllerTarget($controller);
    $route = [
      "params" => [],
      "middlewares" => array_merge(self::$groupMiddlewares, $middlewares),
      "controller" => $target["controller"],
      "controllerInstantiateParams" => $controllerInstantiateParams,
      "controllerHandleMethodName" => $target["handleMethodName"],
    ];

    //* 动态路由：URI 含正则参数
    if (strpos($uri, "{") !== false) {
      $pattern = self::buildParamsPattern($uri);
      self::$paramsRoutes[$type][$method][$pattern] = $route;
    } else {
      self::$staticRoutes[$type][$method][$uri] = $route;
    }
    return self::class;
  }

  /**
   * 构建动态路由正则
   *
   * `{paramName:regex}` → `(?P<paramName>regex)`；`{?paramName:regex}`（可选参数）→ `(?P<paramName>regex)?`
   *
   * @param string $uri 含参数的 URI
   * @return string 正则
   */
  protected static function buildParamsPattern($uri)
  {
    $pattern = preg_replace_callback('/\{(\??)(\w+):([^}]+)\}/', function ($matches) {
      $optional = $matches[1] === "?" ? "?" : "";
      return "(?P<" . $matches[2] . ">" . $matches[3] . ")" . $optional;
    }, $uri);
    return "#^" . $pattern . "$#";
  }

  /**
   * 解析控制器目标
   *
   * - 字符串类名 → controller=类名，handleMethodName=null（默认 data()）
   * - 数组 [类名, 方法名] → controller=类名，handleMethodName=方法名（默认 data）
   * - 闭包 → controller=闭包，handleMethodName=null
   *
   * @param string|array|\Closure $controller
   * @return array{controller: mixed, handleMethodName: string|null}
   */
  protected static function resolveControllerTarget($controller)
  {
    if (is_array($controller)) {
      return [
        "controller" => $controller[0],
        "handleMethodName" => $controller[1] ?? "data",
      ];
    }
    return [
      "controller" => $controller,
      "handleMethodName" => null,
    ];
  }

  /**
   * 注册 GET 路由
   *
   * same() 回调中只传控制器（URI 由 same() 提供）。
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function get($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("common", "get", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册 POST 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function post($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("common", "post", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册 PUT 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function put($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("common", "put", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册 PATCH 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function patch($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("common", "patch", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册 DELETE 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function delete($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("common", "delete", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册 OPTIONS 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function options($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("common", "options", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册 HEAD 路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function head($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("common", "head", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册任意方法路由
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function any($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("any", "*", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 注册异步路由
   *
   * 仅能通过服务器内部 CURL 调用（需 X-Async ），
   * 用于后台任务、内部接口等场景，由 dispatch() 触发。
   *
   * @param string $uri 路由 URI
   * @param string|array|\Closure $controller 控制器
   * @param array $middlewares 路由中间件
   * @param array $controllerInstantiateParams 控制器实例化参数
   * @return string 类名（供链式调用）
   */
  public static function async($uri = null, $controller = null, $middlewares = [], $controllerInstantiateParams = [])
  {
    if (self::$sameUri !== null) {
      $controller = $uri;
      $uri = self::$sameUri;
    }
    return self::register("async", "*", $uri, $controller, $middlewares, $controllerInstantiateParams);
  }

  /**
   * 匹配路由
   *
   * 按优先级匹配：静态 common → 静态 async（async 请求）→ 静态 any → 动态 common → 动态 async → 动态 any。
   *
   * @param Request $request 请求实例
   * @return array|null 匹配到的路由（含 params），未匹配返回 null
   */
  public static function match(Request $request)
  {
    //* command 模式：按命令名匹配（CLI 下 request->uri() 即命中的命令名），不解析 URI
    if (self::$mode === "command") {
      return self::$commands[$request->uri()] ?? null;
    }

    $uri = $request->uri();
    if ($uri !== "/") {
      $uri = ltrim($uri, "/");
    }
    $method = $request->method();
    $isAsync = $request->async();

    //* 1. 静态 common
    if (isset(self::$staticRoutes["common"][$method][$uri])) {
      return self::$staticRoutes["common"][$method][$uri];
    }
    //* 2. 静态 async（async 请求）
    if ($isAsync && isset(self::$staticRoutes["async"][$method][$uri])) {
      return self::$staticRoutes["async"][$method][$uri];
    }
    if ($isAsync && isset(self::$staticRoutes["async"]["*"][$uri])) {
      return self::$staticRoutes["async"]["*"][$uri];
    }
    //* 3. 静态 any
    if (isset(self::$staticRoutes["any"][$method][$uri])) {
      return self::$staticRoutes["any"][$method][$uri];
    }
    if (isset(self::$staticRoutes["any"]["*"][$uri])) {
      return self::$staticRoutes["any"]["*"][$uri];
    }

    //* 4. 动态路由逐条 preg_match
    foreach (["common", "async", "any"] as $type) {
      if ($type === "async" && !$isAsync) {
        continue;
      }
      $methodRoutes = self::$paramsRoutes[$type][$method] ?? self::$paramsRoutes[$type]["*"] ?? [];
      if (!count($methodRoutes)) {
        continue;
      }
      foreach ($methodRoutes as $pattern => $route) {
        if (preg_match($pattern, $uri, $matches)) {
          $params = [];
          foreach ($matches as $key => $value) {
            if (is_string($key)) {
              $params[$key] = $value;
            }
          }
          $route["params"] = $params;
          return $route;
        }
      }
    }

    return null;
  }

  /**
   * 通过内部 CURL 调用异步路由
   *
   * 自动附加 X-Async 请求头，使 async 路由可被 match() 命中。
   *
   * @param string $uri 目标 URI
   * @param array $data 请求数据
   * @param array $headers 附加请求头
   * @param int $timeout 超时秒数
   * @return mixed 响应数据
   */
  public static function dispatch($uri, $data = [], $headers = [], $timeout = 1)
  {
    $result = Curl::init()
      ->url(URL::baseURL() . $uri)
      ->headers(array_merge($headers, ["X-Async" => "true", "X-Ajax" => "true"]))
      ->data($data)
      ->timeout($timeout)
      ->post()
      ->getData();
    return $result;
  }

  /**
   * 注册 CLI 命令
   *
   * 控制器支持：类名（默认调用 handle()）、[类名, 方法名] 或闭包。
   *
   * @param string $name 命令名
   * @param string|array|\Closure $controller 命令控制器
   * @param string $description 命令说明
   * @return string 类名（供链式调用）
   */
  public static function command($name, $controller, $description = "")
  {
    //* 按模式收集：http 模式下不注册 CLI 命令（loadRoutes 按模式加载，避免冗余存储）
    if (self::$mode === "http") {
      return self::class;
    }

    if (is_array($controller)) {
      $target = [
        "controller" => $controller[0],
        "handleMethodName" => $controller[1] ?? "handle",
      ];
    } elseif ($controller instanceof \Closure) {
      $target = [
        "controller" => $controller,
        "handleMethodName" => null,
      ];
    } else {
      $target = [
        "controller" => $controller,
        "handleMethodName" => "handle",
      ];
    }
    self::$commands[$name] = [
      "controller" => $target["controller"],
      "handleMethodName" => $target["handleMethodName"],
      "description" => $description,
    ];
    return self::class;
  }

  /**
   * 获取 CLI 命令表
   *
   * @return array<string, array>
   */
  public static function commands()
  {
    return self::$commands;
  }
}
