<?php

namespace kernel\Foundation;

use isdtjBackend\Controller\Links\Link\GetLinkController;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Network\Curl;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Router
{
  /**
   * 静态路由表
   *
   * @var array
   */
  private static $StaticRoutes = [];
  /**
   * 动态路由表
   *
   * @var array
   */
  private static $ParamsRoutes = [];

  /**
   * 当前是否在路由组中
   *
   * @var boolean
   */
  private static $InGroup = false;
  /**
   * 路由组的中间件
   *
   * @var array
   */
  private static $GroupMiddlewares = [];

  /**
   * 路由前缀
   *
   * @var array
   */
  private static $Prefix = [];
  /**
   * same方法执行时记录的相同URI
   *
   * @var string
   */
  private static $sameURI = null;
  /**
   * 设置路由前缀
   *
   * @param array|string $prefix 前缀，如果传入null即为清除前缀，后续的注册路由不再添加前缀
   * @return Router
   */
  static function prefix($prefix)
  {
    if (is_null($prefix)) {
      self::$Prefix = [];
    }
    if (self::$InGroup) {
      $prefix = is_string($prefix) ? [$prefix] : $prefix;
      foreach ($prefix as $value) {
        array_unshift(self::$Prefix, $value);
      }
    } else {
      self::$Prefix = is_string($prefix) ? [$prefix] : $prefix;
    }

    return new static;
  }

  /**
   * 路由组
   *
   * @param string|string[] $prefix 前缀
   * @param \Closure $callback 创建属于该路由组的路由回调函数
   * @param array $middlewares 子路由们拥有的中间件
   * @return Router
   */
  static function group($prefix, \Closure $callback, $middlewares = [])
  {
    if (!is_array($middlewares)) {
      if (empty($middlewares)) {
        $middlewares = [];
      } else {
        $middlewares = [$middlewares];
      }
    }

    self::$InGroup = true;
    self::$GroupMiddlewares = $middlewares;
    self::$Prefix = is_string($prefix) ? [$prefix] : $prefix;

    $callback();

    return new static;
  }
  /**
   * 注册同一URI不同方法的路由
   *
   * @param string $URI URI
   * @param \Closure $callback 注册不同方法路由的回调函数
   * @return Router
   */
  static function same($URI, \Closure $callback)
  {
    self::$sameURI = $URI;
    $callback();
    self::$sameURI = null;
    return new static;
  }

  /**
   * 注册路由
   *
   * @param string $type 路由类型
   * @param string $method 请求的方法
   * @param string $URI 路由的URI
   * @param Controller|array $controller 命中后执行的控制器
   * @param array $middlewares 路由的中间件
   * @return Router
   */
  static function register($type, $method, $URI, $controller, $middlewares = [])
  {
    $handleMethodName = null;
    if ((is_array($URI) || class_exists($URI)) && is_null($controller)) {
      if (is_array($URI)) {
        $controller = $URI[0];
        $handleMethodName = isset($URI[1]) ? $URI[1] : "data";
      } else {
        $controller = $URI;
      }
      $URI = self::$sameURI;
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
    if (is_array($controller)) {
      $controllerClass = $controller[0];
      $handleMethodName = isset($controller[1]) ? $controller[1] : "data";
      $controller = $controllerClass;
    }

    $HasParamsRoute = preg_match_all("/(?<=\\{)[^}]*(?=\\})/", $URI, $Params);
    if ($HasParamsRoute) {
      $URIParts = explode("/", $URI);
      if (!empty(self::$Prefix)) {
        foreach (self::$Prefix as $prefix) {
          array_unshift($URIParts, $prefix);
        }
      }
      $URIParts = array_filter($URIParts, function ($item) {
        if (empty(trim($item))) return false;
        return true;
      });

      $patterns = [];
      $Params = [];
      foreach ($URIParts as $URIPart) {
        $HasParamPart = preg_match_all("/(?<=\\{)[^}]*(?=\\})/", $URIPart, $Param);
        if ($HasParamPart) {
          $Param = $Param[0][0];
          if (strpos($Param, ":") === false) {
            $Params[$Param] = null;
            array_push($patterns, "/(\w+)");
          } else {
            $ParamSplits = explode(":", $Param);
            $key = trim($ParamSplits[0]);
            $pattern = trim($ParamSplits[1]);

            $NotEssential = false; //* 该参数可有可无的
            if (empty($key)) {
              $key = count($Params);
            }

            $NotEssential = strpos($key, "?") !== false; //* 该参数可有可无的
            if ($NotEssential) {
              $key = substr($key, 1);
            }
            if (empty($key)) {
              array_push($Params, null);
            } else {
              $Params[$key] = null;
            }

            $ParamPattern = trim($pattern);
            if ($ParamPattern) {
              $ParamPattern = $NotEssential ? "/?($ParamPattern)?" : "/($ParamPattern)";
            } else {
              $ParamPattern =  $NotEssential ? "/?(\w+)?" : "/(\w+)";
            }
            array_push($patterns, $ParamPattern);
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
        "params" => $Params,
        "controllerHandleMethodName" => $handleMethodName
      ];
    } else {
      if (!empty(self::$Prefix)) {
        $URI = [
          $URI
        ];
        foreach (self::$Prefix as $prefix) {
          array_unshift($URI, $prefix);
        }
        $URI = implode("/", $URI);
      }
      self::$StaticRoutes[$type][$method][$URI] = [
        "raw" => $URI,
        "uri" => $URI,
        "type" => $type,
        "method" => $method,
        "controller" => $controller,
        "middlewares" => $middlewares,
        "params" => [],
        "controllerHandleMethodName" => $handleMethodName
      ];
    }

    return new static;
  }

  /**
   * 注册get方法的路由
   *
   * @param string|Controller $URI URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function get($URI, $controller = null, $middlewares = [])
  {
    return self::register("common", "get", $URI, $controller, $middlewares);
  }
  /**
   * 注册post方法的路由
   *
   * @param string|Controller $URI URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function post($URI, $controller = null, $middlewares = [])
  {
    return self::register("common", "post", $URI, $controller, $middlewares);
  }
  /**
   * 注册put方法的路由
   *
   * @param string|Controller $URIorController URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function put($URIorController, $controller = null, $middlewares = [])
  {
    return self::register("common", "put", $URIorController, $controller, $middlewares);
  }
  /**
   * 注册patch方法的路由
   *
   * @param string|Controller $URIorController URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function patch($URIorController, $controller = null, $middlewares = [])
  {
    return self::register("common", "patch", $URIorController, $controller, $middlewares);
  }
  /**
   * 注册delete方法的路由
   *
   * @param string|Controller $URIorController URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function delete($URIorController, $controller = null, $middlewares = [])
  {
    return self::register("common", "delete", $URIorController, $controller, $middlewares);
  }
  /**
   * 注册options方法的路由
   *
   * @param string|Controller $URIorController URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function options($URIorController, $controller = null, $middlewares = [])
  {
    return self::register("common", "options", $URIorController, $controller, $middlewares);
  }
  /**
   * 注册async方法的路由
   *
   * @param string|Controller $URIorController URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function async($URIorController, $controller = null, $middlewares = [])
  {
    return self::register("async", "async", $URIorController, $controller, $middlewares);
  }
  /**
   * 注册any方法的路由
   *
   * @param string|Controller $URIorController URI地址，如果是在same运行的闭包函数场景下，该值传入控制器类即可
   * @param Controller|array $controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param array $middlewares 路由中间件
   * @return Router
   */
  static function any($URIorController, $controller = null, $middlewares = [])
  {
    return self::register("any", "any", $URIorController, $controller, $middlewares);
  }

  /**
   * 调用内部路由
   * 调用的是async类型的路由
   * 实际原理是，通过CURL发起一个新的非get请求，请求的地址就是传入的URI，以达到异步调用的效果。
   *
   * @param string $URI 请求的URI
   * @param array $data 发送的数据
   * @param array $headers 请求头
   * @param integer $timeout 请求超时时长
   * @return mixed
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
   * 匹配参数路由
   *
   * @param string $URI 用于匹配的URI
   * @param array $routes 路由表
   * @return array 命中的路由
   */
  static private function matchParamRoute($URI, $routes)
  {
    $matchRoute = null;

    foreach ($routes as $pattern => $route) {
      if (preg_match("/^$pattern$/", $URI)) {
        preg_match_all("/^$pattern$/", $URI, $Params);
        array_shift($Params); //* 弹出第一个，因为匹配的是整个URI

        foreach ($route['params'] as &$item) {
          $item = array_shift($Params);
          if ($item && is_array($item) && count($item)) {
            $item = $item[0];
          }
        }

        $matchRoute = $route;
        break;
      }
    }

    return $matchRoute;
  }
  /**
   * 匹配路由
   *
   * @param Request $request 请求体
   * @return array 命中的路由
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
    if (isset(self::$StaticRoutes['common'][$Method][$URI]) && isset(self::$StaticRoutes['common'][$Method])) {
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
