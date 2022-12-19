<?php

namespace gstudio_kernel\Foundation\Router;

use Closure;
use gstudio_kernel\Foundation\Data\Arr;
use gstudio_kernel\Foundation\Network\Curl;
use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Foundation\Request;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class Router extends RouterPrefix
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
      if (isset(self::$staticRoutes['any'][$uri])) {
        $matchRoute = self::$staticRoutes['any'][$uri];
      } else if (isset(self::$staticRoutes['resource'][$uri])) {
        $matchRoute = self::$staticRoutes['resource'][$uri];
      } else if (isset(self::$staticRoutes['async'][$uri])) {
        $matchRoute = self::$staticRoutes['async'][$uri];
      } else {
        //* 匹配动态路由
        $matchRoute = null;
        if (isset(self::$dynamicRoutes["resource"])) {
          $matchRoute = self::matchDynamicRoute($R, self::$dynamicRoutes["resource"]);
        }
        if (!$matchRoute && isset(self::$dynamicRoutes[$method])) {
          $matchRoute = self::matchDynamicRoute($R, self::$dynamicRoutes[$method]);
        }
      }
    } else {
      $matchRoute = self::$staticRoutes[$method][$uri];
    }
    if (!$matchRoute) return null;
    if ($matchRoute['type'] === "async" || $R->headers("X-Async")) {
      if ($method === "get" || !$R->headers("X-Async") || !in_array($matchRoute['type'], ["async", "resource"])) {
        $matchRoute = null;
      }
    }

    return $matchRoute;
  }
  static function register($type, $method, $uri, $controllerNameOfFunction, $middlewareName = null)
  {
    if (!empty(self::$Prefix)) {
      if (is_array(self::$Prefix)) {
        if (is_array($uri)) {
          $uri = array_merge(self::$Prefix, $uri);
        } else {
          array_push(self::$Prefix, $uri);
          $uri = self::$Prefix;
        }
      } else {
        if (is_array($uri)) {
          array_unshift($uri, self::$Prefix);
        } else {
          if ($uri[0] === "/") {
            $uri = substr($uri, 1);
          }
          $uri = implode("/", [self::$Prefix, $uri]);
        }
      }
    }

    if (is_array($uri)) {
      $uri = array_map(function ($item) {
        return str_replace("/", "\/", trim($item));
      }, $uri);

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
  static function dispatch($uri,  $data = [],  $headers = [],  $timeout = 1)
  {
    $C = new Curl();
    $url = F_BASE_URL . $uri;

    $headers = array_merge([
      "X-Async" => 1,
      "X-Ajax" => 1
    ], $headers);
    $C->url($url)->headers($headers)->timeout($timeout)->https(false)->data($data)->post();
    if ($C->errorNo()) {
      return $C->error();
    }
    return $C->getData();
  }
  static function redirect($targetUrl, $statusCode = 302)
  {
    header("Location: $targetUrl", true, $statusCode);
  }

  static function group($prefix, Closure $callBack)
  {
    $oldPrefix = self::$Prefix;
    Router::prefix($prefix);

    $callBack();

    Router::prefix($oldPrefix);
  }
}
