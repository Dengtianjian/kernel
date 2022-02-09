<?php

namespace kernel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Application;
use kernel\Middleware as Middleware;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Foundation\Router;
use kernel\Foundation\Config as Config;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Lang;
use kernel\Foundation\Log;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalExtensionsMiddleware;
use kernel\Middleware\GlobalMultipleEncodeMiddleware;

class App extends Application
{
  /**
   * 构造函数
   *
   * @param string $pluginId 应用id
   */
  function __construct($pluginId = null)
  {
    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\Exception::receive");

    //* 检查是否已经初始化了
    if (!file_exists(F_APP_ROOT . "/Iuu/.version")) {
      header("Access-Control-Allow-Origin:*");
      header('Access-Control-Allow-Methods:*');
      header('Access-Control-Allow-Headers:*');
      header('Access-Control-Max-Age:86400');
      header('Access-Control-Allow-Credentials: false');
      Log::record("接收到请求，无法处理，还未初始化程序");
      Response::error(500, "SystemNotInitialized:500000", "服务器错误", [], [
        "content" => "还未初始化系统"
      ]);
    }

    //* 初始化全局数据
    self::initGlobalVariables($pluginId);

    $this->pluginId = $pluginId;
    $this->pluginPath = F_ROOT . "/$pluginId";
    $this->uri = \addslashes($_GET['uri']);

    Lang::load(F_KERNEL_ROOT . "/Langs/" . CHARSET . ".php"); //* 加载语言包文件
    ErrorCode::load(F_KERNEL_ROOT . "/Foundation/Exception/ErrorCodes.php"); //* 加载错误码

    include_once(F_KERNEL_ROOT . "/Routes.php"); //* 载入kernel用到的路由
    include_once($this->pluginPath . "/Routes.php"); //* 载入路由

    $GLOBALS['app'] = $this;
  }
  function init()
  {
    $this->setMiddlware(Middleware\GlobalSetsMiddleware::class);

    $request = new Request();
    $this->request = $request;

    //* 载入扩展
    if (Config::get("extensions")) {
      $this->loadExtensions();
      $this->setMiddlware(GlobalExtensionsMiddleware::class);
    }

    if ($this->request->ajax() === NULL) {
      $this->setMiddlware(GlobalMultipleEncodeMiddleware::class);
    }
    $router = Router::match($request->uri, $request);
    if ($router && $router['type'] === "api" && $router['method'] !== 'any' && $this->request->ajax() === null) {
      Response::error("METHOD_NOT_ALLOWED");
    }
    $request->setParams($router['params']);
    header("Access-Control-Allow-Origin:*");
    header('Access-Control-Allow-Methods:*');
    header('Access-Control-Allow-Headers:*');
    header('Access-Control-Max-Age:86400');
    header('Access-Control-Allow-Credentials: true');
    $request->router = $router;
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
  public static function hook($pluginId)
  {
    self::initGlobalVariables($pluginId);
  }
}
