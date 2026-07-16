<?php

namespace kernel\Foundation;

use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\HTTP\Request;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/**
 * 路由注册与匹配
 *
 * 纯静态类，维护全局路由表，提供链式 DSL 风格的 API。
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
 * 1. 静态路由 `$StaticRoutes['common'][$method][$uri]`
 * 2. 静态 `async` 路由
 * 3. 静态 `any` 路由
 * 4. 动态路由逐条 `preg_match`
 */
class Router
{
  /**
   * 静态路由表
   *
   * 结构：`$StaticRoutes[$type][$method][$uri] = route`
   *
   * @var array{common: array, async: array, any: array}
   */
  private static $StaticRoutes = [];

  /**
   * 动态路由表（含正则参数的 URI）
   *
   * 结构：`$ParamsRoutes[$type][$method][$pattern] = route`
   *
   * @var array{common: array, async: array, any: array}
   */
  private static $ParamsRoutes = [];

  /** 是否处于 group() 回调中 */
  private static $InGroup = false;

  /** group() 上下文中的共享中间件 */
  private static $GroupMiddlewares = [];

  /**
   * 路由前缀栈
   *
   * 由 group() 或 prefix() 设置，register() 中拼接到 URI 前。
   *
   * @var string[]
   */
  private static $Prefix = [];

  /**
   * same() 上下文中的共用 URI
   *
   * same() 回调中注册路由时，register() 从此取值。
   *
   * @var string|null
   */
  private static $sameURI = null;
  /**
   * 设置路由前缀
   *
   * 后续注册的路由 URI 会自动拼接此前缀。
   *
   * @param string|string[]|null $prefix 前缀字符串或数组；传 null 清除前缀
   * @param bool                 $append 是否追加到已有前缀（而非替换）
   */
  static function prefix($prefix, $append = false)
  {
    if (is_null($prefix)) {
      self::$Prefix = [];
    } else {
      $prefix = is_string($prefix) ? [$prefix] : $prefix;
      if (self::$InGroup) {
        foreach ($prefix as $value) {
          array_push(self::$Prefix, $value);
        }
      } else {
        if ($append) {
          array_push(self::$Prefix, ...$prefix);
        } else {
          self::$Prefix = $prefix;
        }
      }
    }

    return new static;
  }

  /**
   * 路由组
   *
   * 为回调内注册的路由统一添加前缀和中间件。支持嵌套 group。
   *
   * @param string|string[] $prefix      路由前缀
   * @param \Closure        $callback    注册子路由的回调
   * @param array           $middlewares 组内路由共享的中间件
   */
  static function group($prefix, \Closure $callback, $middlewares = [])
  {
    if (!is_array($middlewares)) {
      $middlewares = empty($middlewares) ? [] : [$middlewares];
    }

    $OldInGroup = self::$InGroup;
    $OldGroupMiddlewares = self::$GroupMiddlewares;
    $OldPrefix = self::$Prefix;

    self::$InGroup = true;
    self::$GroupMiddlewares = $middlewares;
    self::$Prefix = is_string($prefix) ? [$prefix] : $prefix;

    $callback();

    self::$Prefix = $OldPrefix;
    self::$GroupMiddlewares = $OldGroupMiddlewares;
    self::$InGroup = $OldInGroup;
  }
  /**
   * 同一 URI 注册多个 HTTP 方法的路由
   *
   * 回调内调用 Router::get()/post() 等方法时，URI 参数自动取 self::$sameURI，
   * 只需传入控制器类名即可。
   *
   * @param string   $URI      共用 URI
   * @param \Closure $callback 注册不同方法路由的回调
   */
  static function same($URI, \Closure $callback)
  {
    self::$sameURI = $URI;
    $callback();
    self::$sameURI = null;
    return new static;
  }

  /**
   * 从控制器配置中解析 [类名, 方法名] 或纯类名
   *
   * @param string|array $target 控制器类名或 [类名, 方法名] 数组
   * @return array{string, string} [类名, 方法名]
   */
  static private function resolveControllerTarget($target): array
  {
    if (is_array($target)) {
      return [$target[0], isset($target[1]) ? $target[1] : "data"];
    }
    return [$target, "data"];
  }

  /**
   * 注册路由（底层方法）
   *
   * 根据 URI 是否含参数自动分类为静态或动态路由。
   * same() 上下文下自动从 self::$sameURI 取 URI；group() 上下文下自动合并中间件和前缀。
   *
   * @param string                    $type                       路由类型："common" | "async" | "any"
   * @param string                    $method                     请求方法
   * @param string|array|null         $URI                        URI；same() 上下文中传入控制器类名
   * @param string|array|null         $controller                 控制器类名或 [类名, 方法名]
   * @param array                     $middlewares                路由中间件
   * @param array                     $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function register($type, $method, $URI, $controller, $middlewares = [], $ControllerInstantiateParams = [])
  {
    // same() 上下文：$URI 位置传入控制器类名或 [类名, 方法名]，$controller 为 null
    if (self::$sameURI !== null && is_null($controller)) {
      [$controller, $handleMethodName] = self::resolveControllerTarget($URI);
      $URI = self::$sameURI;
    } else {
      $handleMethodName = "data";
    }

    if (!is_array($middlewares)) {
      if (empty($middlewares)) {
        $middlewares = [];
      } else {
        $middlewares = [$middlewares];
      }
    }
    if (self::$InGroup && is_array(self::$GroupMiddlewares)) {
      foreach (self::$GroupMiddlewares as $middleware) {
        array_unshift($middlewares, $middleware);
      }
    }

    // $controller 位置传入 [类名, 方法名] 数组
    if (is_array($controller)) {
      [$controller, $handleMethodName] = self::resolveControllerTarget($controller);
    }

    if (!empty(self::$Prefix)) {
      $prefix = self::$Prefix;
      if (is_array($prefix)) {
        $prefix = implode("/", $prefix);
      }
      if (substr($prefix, strlen($prefix) - 1) === "/") {
        $prefix = substr($prefix, 0, strlen($prefix) - 1);
      }
      $URI = implode("/", array_filter([
        $prefix,
        $URI
      ], function ($item) {
        return $item;
      }));
    }
    $HasParamsRoute = preg_match_all("/(?<=\\{)[^}]*(?=\\})/", $URI, $MatchParams);
    if ($HasParamsRoute) {
      $URIParams = [];
      foreach ($MatchParams as $item) {
        array_push($URIParams, ...$item);
      }

      $replaceURI = $URI;
      foreach ($URIParams as $index => $value) {
        $replaceURI = str_replace($value, "{$index}", $replaceURI);
      }

      $URIParts = explode("/", $replaceURI);
      $URIParts = array_filter($URIParts, function ($item) {
        if (empty(trim($item)))
          return false;
        return true;
      });

      $patterns = [];
      $params = [];
      foreach ($URIParts as $URIPart) {
        $HasParamPart = preg_match_all("/(?<=\\{)[^}]*(?=\\})/", $URIPart, $Param);
        if ($HasParamPart) {
          $Param = $Param[0][0];
          $Param = $URIPart = $URIParams[$Param];
          if (strpos($Param, ":") === false) {
            $params[$Param] = null;
            array_push($patterns, "/(\w+)");
          } else {
            $ParamSplits = explode(":", $Param);
            $key = trim($ParamSplits[0]);
            $pattern = trim($ParamSplits[1]);

            $NotEssential = false; //* 该参数可有可无的
            if (empty($key)) {
              $key = count($params);
            }

            $NotEssential = strpos($key, "?") !== false; //* 该参数可有可无的
            if ($NotEssential) {
              if (substr($key, 0, 1) === "?") {
                $key = substr($key, 1);
              } else {
                $key = substr($key, 0, strlen($key) - 1);
              }
            }
            if (empty($key)) {
              array_push($params, null);
            } else {
              $params[$key] = null;
            }

            $paramPattern = trim($pattern);
            if ($paramPattern) {
              if (!preg_match("/^\(.+\)$/", $paramPattern)) {
                $paramPattern = "({$paramPattern})";
              }
              $paramPattern = $NotEssential ? "/?{$paramPattern}?" : "/{$paramPattern}";
            } else {
              $paramPattern = $NotEssential ? "/?(\w+)?" : "/(\w+)";
            }
            array_push($patterns, $paramPattern);
          }
        } else {
          if (count($patterns) === 0) {
            array_push($patterns, $URIPart);
          } else {
            array_push($patterns, "/$URIPart");
          }
        }
      }

      $pattern = implode("", $patterns);
      $pattern = str_replace("/", "\/", $pattern);

      self::$ParamsRoutes[$type][$method][$pattern] = [
        "raw" => $URI,
        "uri" => $pattern,
        "type" => $type,
        "method" => $method,
        "controller" => $controller,
        "middlewares" => $middlewares,
        "params" => $params,
        "controllerHandleMethodName" => $handleMethodName,
        "controllerInstantiateParams" => $ControllerInstantiateParams
      ];
    } else {
      self::$StaticRoutes[$type][$method][$URI] = [
        "raw" => $URI,
        "uri" => $URI,
        "type" => $type,
        "method" => $method,
        "controller" => $controller,
        "middlewares" => $middlewares,
        "params" => [],
        "controllerHandleMethodName" => $handleMethodName,
        "controllerInstantiateParams" => $ControllerInstantiateParams
      ];
    }

    return new static;
  }

  /**
   * 注册 GET 路由
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function get($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("common", "get", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }
  /**
   * 注册 POST 路由
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function post($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("common", "post", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }
  /**
   * 注册 PUT 路由
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function put($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("common", "put", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }
  /**
   * 注册 PATCH 路由
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function patch($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("common", "patch", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }
  /**
   * 注册 DELETE 路由
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function delete($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("common", "delete", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }
  /**
   * 注册 OPTIONS 路由
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function options($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("common", "options", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }
  /**
   * 注册异步路由（仅内部 dispatch / Curl 调用）
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function async($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("async", "async", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }
  /**
   * 注册通配路由（匹配任意 HTTP 方法）
   *
   * @param string|array $URI                        URI；same() 上下文中直接传控制器类名
   * @param string|array|null $controller            控制器类名或 [类名, 方法名]
   * @param array              $middlewares           路由中间件
   * @param array              $ControllerInstantiateParams 控制器实例化额外参数
   */
  static function any($URI, $controller = null, $middlewares = [], $ControllerInstantiateParams = [])
  {
    return self::register("any", "any", $URI, $controller, $middlewares, $ControllerInstantiateParams);
  }

  /**
   * 异步调用内部路由
   *
   * 通过 Curl 向自身发起 POST 请求（带 X-Async 头），目标必须在 async 类型路由中注册。
   *
   * @param string $URI     目标路由 URI
   * @param array  $data    发送的 POST 数据
   * @param array  $headers 额外请求头
   * @param int    $timeout 超时时间（秒）
   * @return mixed Curl 响应数据或错误信息
   */
  static function dispatch($URI, $data = [], $headers = [], $timeout = 1)
  {
    $C = new Curl();
    $URL = F_BASE_URL . $URI;

    $headers = array_merge([
      "X-Async" => 1,
      "X-Ajax" => 1
    ], $headers);
    $C->url($URL)->headers($headers)->timeout($timeout)->https(false)->data($data)->post();
    if ($C->errorNo()) {
      return $C->error();
    }
    return $C->getData();
  }

  /**
   * 逐条匹配动态路由
   *
   * @param string $URI    请求 URI
   * @param array  $routes 动态路由表（pattern => route）
   * @return array|null 匹配到的路由，未匹配返回 null
   */
  static private function matchParamRoute($URI, $routes)
  {
    foreach ($routes as $pattern => $route) {
      if (preg_match("/^$pattern$/u", $URI, $Params)) {
        array_shift($Params);

        foreach ($route['params'] as &$item) {
          $item = array_shift($Params);
          if ($item && is_array($item) && count($item)) {
            $item = $item[0];
          }
        }

        return $route;
      }
    }

    return null;
  }

  /**
   * 匹配路由
   *
   * 匹配优先级：静态 common → 静态 async → 静态 any → 动态 common → 动态 async → 动态 any
   *
   * @param Request $request 请求对象
   * @return array|null 匹配到的路由信息，未匹配返回 null
   */
  static function match(Request $request)
  {
    $Method = $request->method;
    $URI = $request->URI;
    $matchRoute = null;
    if (strlen($URI) > 1 && $URI[0] === "/") {
      $URI = substr($URI, 1);
    }

    //* 优先匹配静态路由，如果没有的话就遍历动态路由，每一个去匹配
    if (isset(self::$StaticRoutes['common'][$Method]) && isset(self::$StaticRoutes['common'][$Method][$URI])) {
      $matchRoute = self::$StaticRoutes['common'][$Method][$URI];
    } else {
      if (isset(self::$StaticRoutes['async']["async"][$URI])) {
        $matchRoute = self::$StaticRoutes['async']["async"][$URI];
      }
      if (isset(self::$StaticRoutes['any']["any"][$URI])) {
        $matchRoute = self::$StaticRoutes['any']["any"][$URI];
      }
    }

    if (!$matchRoute) {
      //* 匹配参数路由
      if (isset(self::$ParamsRoutes['common']) && isset(self::$ParamsRoutes['common'][$Method])) {
        $matchRoute = self::matchParamRoute($URI, self::$ParamsRoutes['common'][$Method]);
      }

      if (!$matchRoute && isset(self::$ParamsRoutes['async']) && isset(self::$ParamsRoutes['async']['async'])) {
        $matchRoute = self::matchParamRoute($URI, self::$ParamsRoutes['async']['async']);
      }
      if (!$matchRoute && isset(self::$ParamsRoutes['any']) && isset(self::$ParamsRoutes['any']['any'])) {
        $matchRoute = self::matchParamRoute($URI, self::$ParamsRoutes['any']['any']);
      }
    }

    if ($matchRoute && $matchRoute['type'] === "async") {
      if ($Method === "get" || !$request->async() || !$request->ajax()) {
        $matchRoute = null;
      }
    }

    return $matchRoute;
  }
}
