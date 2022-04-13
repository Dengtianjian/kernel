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
  static function match(Request $R)
  {
    $method = \strtolower($R->method);
    $uri = $R->uri;
    
    //* 优先匹配静态路由，如果没有的话就遍历动态路由，每一个去匹配
    if (!self::$staticRoutes[$method][$uri]) {
      if (self::$staticRoutes['any'][$uri]) {
        return self::$staticRoutes['any'][$uri];
      }

      //* 匹配动态路由
      $dynamicRoutes = self::$dynamicRoutes[$method];
      if (!$dynamicRoutes) return null;
      $params = [];
      $matchRoute = null;
      foreach ($dynamicRoutes as $uriRegexp => $route) {
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
    $matchRoute = self::$staticRoutes[$method][$uri];;
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
              $params[$key] = "";
              $uriItem = "(.+?)";
            }
          } else {
            $params[$key] = "";
            $uriItem = "($uriItem)";
          }
          $uriParts[$key] = $uriItem;
        }
      } else {
        foreach ($uriParts as &$uriItem) {
          $params[$uriItem] = "";
          $uriItem = "(.+?)";
        }
      }
      $regexp = "/^\/" . implode("\/", $uriParts) . "$/";
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
