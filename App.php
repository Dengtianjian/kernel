<?php

namespace kernel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Application;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Log;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalExtensionsMiddleware;

class App extends Application
{
  public $scenes = "ending";
  function __construct(string $appId)
  {
    define("F_APP_ID", $appId);
    define("F_APP_ROOT", F_ROOT . "/$appId");
    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\Exception::receive");

    //* 初始化全局数据
    $this->initAppStore();
    //* 初始化配置
    $this->initConfig();

    ErrorCode::load(F_KERNEL_ROOT . "/Foundation/Exception/ErrorCodes.php"); //* 加载错误码

    include_once(F_KERNEL_ROOT . "/Routes.php"); //* 载入kernel用到的路由
    include_once(F_APP_ROOT . "/Routes.php"); //* 载入路由

    $request = new Request();
    $this->request = $request;
  }
  function init()
  {
    //* 载入扩展
    if (Config::get("extensions")) {
      $this->loadExtensions();
      $this->setMiddlware(GlobalExtensionsMiddleware::class);
    }

    header("Access-Control-Allow-Origin:*");
    header('Access-Control-Allow-Methods:*');
    header('Access-Control-Allow-Headers:*');
    header('Access-Control-Max-Age:86400');
    header('Access-Control-Allow-Credentials: true');

    $router = Router::match($this->request);
    if (!$router) {
      Response::error("METHOD_NOT_ALLOWED");
    }
    if (isset($router['params'])) {
      $this->request->setParams($router['params']);
    }
    $this->request->router = $router;
    $executeMiddlewareResult = $this->executiveMiddleware();
    $this->router = $router;
    if (!$router) {
      Response::error("ROUTE_DOES_NOT_EXIST");
    }

    if ($executeMiddlewareResult === false) {
      Response::error("MIDDLEWARE_EXECUTION_ERROR");
      return;
    }

    $result = $this->executiveController();
    Response::success($result);
  }
}
