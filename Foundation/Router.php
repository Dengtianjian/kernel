<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Network\Curl;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Router
{
  private static $staticRoutes = [];
  private static $dynamicRoutes = [];
  static function get($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("api", "get", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function post($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("api", "post", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function put($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("api", "put", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function patch($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("api", "patch", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function delete($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("api", "delete", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function options($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("api", "options", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function async($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("async", "post", $uri, $controllerNameOfFunction, $middlewareName);
  }
  // ! 待废弃
  static function view($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("view", "get", $uri, $controllerNameOfFunction, $middlewareName);
  }
  // ! 待废弃
  static function postView($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("view", "post", $uri, $controllerNameOfFunction, $middlewareName);
  }
  // ! 待废弃
  static function putView($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("view", "put", $uri, $controllerNameOfFunction, $middlewareName);
  }
  // ! 待废弃
  static function patchView($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("view", "patch", $uri, $controllerNameOfFunction, $middlewareName);
  }
  // ! 待废弃
  static function deleteView($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("view", "delete", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function any($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("api", "any", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static function resource($uri, $controllerNameOfFunction, $middlewareName = null)
  {
    self::register("resource", "resource", $uri, $controllerNameOfFunction, $middlewareName);
  }
  static private function matchDynamicRoute(Request $R, $routes)
  {
    $uri = $R->uri;

    $params = [];
    $matchRoute = null;

    foreach ($routes as $uriRegexp => $route) {
      if (preg_match($uriRegexp, $uri, $params)) {
        preg_match_all($uriRegexp, $uri, $pg);
        $params = array_slice($params, 1);
        $matchRoute = $route;
        $paramKeys = array_keys($route['params']);
        foreach ($params as $index => $value) {
          $matchRoute['params'][$paramKeys[$index]] = $value;
        }
        break;
      }
    }

    return $matchRoute;
  }
  static function match(Request $R)
  {
    $method = \strtolower($R->method);
    $uri = $R->uri;

    //* 优先匹配静态路由，如果没有的话就遍历动态路由，每一个去匹配
    if (!isset(self::$staticRoutes[$method][$uri])) {
      if (self::$staticRoutes['any'][$uri]) {
        return self::$staticRoutes['any'][$uri];
      }

      //* 匹配动态路由
      $matchRoute = null;
      if (isset(self::$dynamicRoutes["resource"])) {
        $matchRoute = self::matchDynamicRoute($R, self::$dynamicRoutes["resource"]);
      }
      if (!$matchRoute && isset(self::$dynamicRoutes[$method])) {
        $matchRoute = self::matchDynamicRoute($R, self::$dynamicRoutes[$method]);
      }

      return $matchRoute;
    }
    $matchRoute = self::$staticRoutes[$method][$uri];
    if ($matchRoute['type'] === "async") {
      if ($method !== "post" || !$R->headers("X-Async")) {
        return null;
      }
    }

    return self::$staticRoutes[$method][$uri];
  }
  static function register($type, $method, $uri, $controllerNameOfFunction, $middlewareName = null)
  {
    if (is_array($uri)) {
      $regexp = "";
      $params = [];
      $uriParts = $uri;
      if (Arr::isAssoc($uri)) {
        $uriParts = [];
        foreach ($uri as $key => $uriItem) {
          if (is_numeric($key) || $uriItem === "") {
            if (is_numeric($key)) {
              $uriParts[$uriItem] = $uriItem;
              continue;
            } else {
              if (!$uriItem) {
                $params[$key] = "";
                $uriItem = "(.+?)";
              }
            }
          } else {
            $params[$key] = "";
            $uriItem = "($uriItem)";
          }
          $uriParts[$key] = $uriItem;
        }
      } else {
        foreach ($uriParts as &$uriItem) {
          if (!$uriItem) {
            $params[$uriItem] = "";
            $uriItem = "(.+?)";
          }
        }
      }

      $regexp = "/^\/?" . implode("\/", $uriParts) . "$/";
      self::$dynamicRoutes[$method][$regexp] = [
        "controller" => $controllerNameOfFunction,
        "middleware" => $middlewareName,
        "type" => $type,
        "method" => $method,
        "uri" => $regexp,
        "rawUri" => $uri,
        "params" => $params
      ];
      return self::$staticRoutes;
    }
    //* 静态路由
    self::$staticRoutes[$method][$uri] = [
      "controller" => $controllerNameOfFunction,
      "middleware" => $middlewareName,
      "type" => $type,
      "method" => $method
    ];
    return self::$staticRoutes;
  }
  static function dispatch(string $uri, array $data = [], array $headers = [], int $timeout = 1)
  {
    $C = new Curl();
    $url = F_BASE_URL . $uri;

    $headers = array_merge([
      "X-Async" => 1,
      "X-Ajax" => 1
    ], $headers);
    $C->url($url)->headers($headers)->timeout($timeout)->https(false)->data($data)->post();
    return $C->getData();
  }
  static function redirect($targetUrl, $statusCode = 302)
  {
    header("Location: $targetUrl", true, $statusCode);
  }
}
