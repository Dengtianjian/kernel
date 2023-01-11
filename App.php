<?php

namespace gstudio_kernel;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

function errorHandler()
{
  if (\func_num_args() > 0) {
    debug(\func_get_args());
  } else {
    debug(\error_get_last());
  }
}

// error_reporting(\E_ALL);
\set_error_handler("gstudio_kernel\\errorHandler", 0);

use gstudio_kernel\Foundation\Application;
use gstudio_kernel\Middleware as Middleware;
use gstudio_kernel\Foundation\Request;
use gstudio_kernel\Foundation\Response;
use gstudio_kernel\Foundation\Router;
use gstudio_kernel\Foundation\Config as Config;
use gstudio_kernel\Foundation\Date;
use gstudio_kernel\Foundation\Exception\ErrorCode;
use gstudio_kernel\Foundation\File;
use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Middleware\GlobalMultipleEncodeMiddleware;

class App extends Application
{
  /**
   * 构造函数
   *
   * @param string $pluginId 应用id
   */
  function __construct($pluginId = null, $hook = false)
  {
    $lifeTimes = [
      "start" => Date::milliseconds()
    ];
    Response::intercept(function () use ($lifeTimes) {
      $lifeTimes['end'] = Date::milliseconds();
      $lifeTimes['timeUsed'] = $lifeTimes['end'] - $lifeTimes['start'];
      $lifeTimes['unit'] = "毫秒";
      Response::add([
        "lifeTimes" => $lifeTimes,
      ]);
    });

    $GLOBALS['App'] = $this;
    $this->request = new Request();
    $this->Hook = $hook;

    $this->pluginId = $pluginId;
    $this->uri = \addslashes($_GET['uri']);

    \set_exception_handler("gstudio_kernel\Foundation\Exception\Exception::receive");

    $this->defineConstants();

    //* 初始化全局数据
    $this->initAppStore();
    //* 初始化配置
    $this->initConfig();

    $this->loadLang();
    ErrorCode::load(F_KERNEL_ROOT . "/ErrorCodes.php"); //* 加载错误码

    include_once(F_KERNEL_ROOT . "/Routes.php"); //* 载入kernel路由
    include_once(F_APP_URL . "/Routes.php"); //* 载入当前应用路由
  }
  function defineConstants()
  {
    global $_G;
    define("F_APP_ID", $this->pluginId);
    define("F_APP_ROOT", File::genPath(DISCUZ_ROOT, "source/plugin", $this->pluginId));
    define("F_APP_URL", File::genPath("source/plugin", $this->pluginId));
    define("F_APP_BASE", File::genPath("source/plugin", $this->pluginId));
    define("F_APP_DATA", File::genPath("data/plugindata", $this->pluginId));
    define("F_KERNEL_ROOT", "source/plugin/gstudio_kernel");
    define("F_KERNEL", true);
    define("F_CACHE_KEY", time());

    //* 获取URL地址
    $baseUrl = $_G['siteurl'];
    if ($baseUrl[strlen($baseUrl) - 1] === "/") {
      $baseUrl = substr($baseUrl, 0, strlen($baseUrl) - 1);
    }
    define("F_BASE_URL", $baseUrl);
  }
  function init()
  {
    header("Access-Control-Allow-Origin:*");
    header('Access-Control-Allow-Methods:*');
    header('Access-Control-Allow-Headers:*');
    header('Access-Control-Max-Age:86400');
    header('Access-Control-Allow-Credentials: true');

    $request = $this->request;

    //* 设置附件目录
    $this->setAttachmentPath();

    //* 载入扩展
    if (Config::get("extensions")) {
      $this->loadExtensions();
    }

    if ($this->request->ajax() === NULL) {
      $this->setMiddlware(GlobalMultipleEncodeMiddleware::class);
    }

    // $executeMiddlewareResult = $this->executiveMiddleware();

    $router = Router::match($request);

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
  public function hook($uri)
  {
    $this->request->set($uri, "get");
  }
}
