<?php

namespace gstudio_kernel\Foundation;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use Error;
use gstudio_kernel\Model\ExtensionsModel;

class Application
{
  protected $pluginId = null; //* 当前插件ID
  protected $uri = null; //* 请求的URI
  protected $globalMiddlware = []; //*全局中间件
  protected $router = null; //* 路由相关
  public $request = null; //* 请求相关
  protected $Hook = null; //* hook钩子模式
  private function __clone()
  {
  }
  private function __construct()
  {
  }
  public function __get($name)
  {
    return $this->$name;
  }
  /**
   * 获取当前实例
   *
   */
  public static function ins()
  {
    return $GLOBALS['app'];
  }
  function setMiddlware($middlwareNameOfFunction, $params = [])
  {
    array_push($this->globalMiddlware, [
      "function" => $middlwareNameOfFunction,
      "params" => $params
    ]);
  }
  protected function executiveController()
  {
    $method = strtolower($this->request->method);
    $controllerParams = $this->router['controller'];
    $executeFunName = null;
    if (is_array($controllerParams)) {
      $length = count($controllerParams);
      $controller = $controllerParams[0];
      if ($length === 2) {
        $executeFunName = $controllerParams[1];
      }
    } else {
      $controller = $controllerParams;
    }

    if (\is_callable($controller)) {
      return $controller($this->request);
    } else {
      $instance = new $controller($this->request);

      if (empty($executeFunName)) {
        if ($this->router['type'] === "async" || $this->request->async()) {
          if (strtolower($method) === "get") {
            Response::error(500, "500:AsyncControlerNotAllowGetMethodRequest", Lang::value("kernel/request/disallowGetRequests"));
          }
          if (!method_exists($instance, "async") && $this->router['type'] === "resource") {
            Response::error(500, "500:ControllerMissingAsyncFunction", Lang::value("kernel/serverError"), [], Lang::value("kernel/controller/asyncMethodIsMissing"));
          } else if (!method_exists($instance, "data") && $this->router['type'] === "async") {
            if (!method_exists($instance, "post")) {
              Response::error(500, "500:ControllerMissingDataHandlerFunction", Lang::value("kernel/serverError"), [], Lang::value("kernel/controller/dataOrPostMethodIsMissing"));
            }
          }
        }
        if ($this->router['type'] === "resource" && $this->request->async()) {
          $executeFunName = "async";
        } else if ($this->router['type'] === "async") {
          $executeFunName = "data";
          if (!method_exists($instance, $executeFunName)) {
            $executeFunName = "post";
          }
        } else if (method_exists($instance, $method)) {
          $executeFunName = $method;
        } else {
          if (!method_exists($instance, "data")) {
            throw new Error(Lang::value("kernel/controller/dataMethodIsMissing"));
          }
          $executeFunName = "data";
        }
      }

      $result = $instance->{$executeFunName}($this->request);

      if ($this->request->ajax() === NULL) {
        View::outputFooter();
      } else {
        if (gettype($instance->serialization) === "string" || (is_array($instance->serialization) && count($instance->serialization) > 0)) {
          if (gettype($instance->serialization) === "array") {
            $ruleName = "serializer_" . time();
            Serializer::addRule($ruleName, $instance->serialization);
            $instance->serialization = $ruleName;
          }
          $result = Serializer::serialization($instance->serialization, $result);
        }
      }

      return $result;
    }
  }
  protected function executiveMiddleware()
  {
    $middlewares = $this->globalMiddlware;
    if (isset($this->router['middleware']) && $this->router['middleware']) {
      if (\is_array($this->router['middleware'])) {
        $middlewares = \array_merge($this->router['middleware']);
      } else {
        $middlewares[] = $this->router['middleware'];
      }
    }

    $middlewareCount = count($middlewares);
    if ($middlewareCount === 0) {
      return;
    }
    $executeCount = 0;

    foreach ($middlewares as $middlewareItem) {
      $middlewareFunction = $middlewareItem['function'];
      $middlewareFunctionParams = $middlewareItem['params'];
      if (\is_callable($middlewareFunction)) {
        $middlewareFunction(function () use (&$executeCount) {
          $executeCount++;
        }, $this->request, $middlewareFunctionParams);
      } else {
        $middlewareInstance = new $middlewareFunction();
        $isNext = false;
        $middlewareInstance->handle(function () use (&$isNext) {
          $isNext = true;
        }, $this->request, $middlewareFunctionParams);
        if ($isNext == false) {
          break;
        } else {
          $executeCount++;
        }
      }
    }

    return $executeCount === $middlewareCount;
  }
  /**
   * 加载语言包
   *
   * @return void
   */
  protected function loadLang()
  {
    $charset = strtoupper(CHARSET);
    include_once(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Langs/$charset.php");
    $langDirPath = F_APP_BASE . "/Langs/";
    if (\file_exists($langDirPath)) {
      $langFilePath = F_APP_BASE . "/Langs/$charset.php";
      if (\file_exists($langFilePath)) {
        include_once($langFilePath);
      }
    }
    Store::setApp([
      "langs" => Lang::all()
    ]);
  }
  /**
   * 载入扩展
   * 获取已开启的扩展，然后访问扩展Main入口文件，执行handle方法
   *
   * @return void
   */
  protected function loadExtensions()
  {
    $EM = new ExtensionsModel();
    $enabledExtensions = $EM->where("enabled", 1)->getAll();
    foreach ($enabledExtensions as $extensionItem) {
      $mainFilepath = DISCUZ_ROOT . $extensionItem['path'] . "/Main.php";
      if (!\file_exists($mainFilepath)) {
        Response::error(500, 500, $extensionItem['name'] . " " . Lang::value("extensionFileCorrupted"));
      }
      $namespace = "\\" . $extensionItem['plugin_id'] . "\\Extensions\\" . $extensionItem['extension_id'] . "\\Main";
      if (!\class_exists($namespace)) {
        Response::error(500, 500, $extensionItem['name'] . " " . Lang::value("extensionFileCorrupted"));
      }
      $MainInstance = new $namespace();
      $MainInstance->handle();
    }
  }
  protected function setAttachmentPath()
  {
    if (Config::get("attachmentPath") === NULL) {
      $attachmentDir = File::genPath(\getglobal("setting/attachurl"), "plugin", F_APP_ID);
      if (!is_dir(DISCUZ_ROOT . $attachmentDir)) {
        \mkdir(DISCUZ_ROOT . $attachmentDir, 0777, true);
      }
      Config::set([
        "attachmentPath" => $attachmentDir
      ]);
    }
  }
  protected function initAppStore()
  {
    //* 存放全局用到的数据
    $__App = [
      "id" => F_APP_ID, //* 当前运行中的应用ID
      "rewriteURL" => [], //* 重写的URL
      "mode" => Config::get("mode", F_APP_ID), //* 当前运行模式
      "langs" => [], //* 字典
      "kernel" => [
        "root" => F_KERNEL_ROOT,
        "assets" => File::genPath(F_KERNEL_ROOT, "Assets"),
        "views" => File::genPath(F_KERNEL_ROOT, "Views"),
        "assetsUrl" => File::genPath(F_KERNEL_ROOT, "Assets"),
        "viewsUrl" => File::genPath(F_KERNEL_ROOT, "Views"),
      ], //* 内核
      "addon" => [ //* 当前运行中的应用信息
        "id" => $this->pluginId,
        "root" => F_APP_BASE,
        "assets" => File::genPath(F_APP_URL, "Assets"),
        "views" => File::genPath(F_APP_URL, "Views")
      ]
    ];
    Store::setApp($__App);
  }
  protected function initConfig()
  {
    $fileBase = F_APP_BASE;
    $configFilePath = F_APP_BASE . "/Config.php";
    if (!file_exists($configFilePath)) {
      $fileBase .= "/Configs";
      $configFilePath = "$fileBase/Config.php";
    }
    Config::read($configFilePath);

    //* 模式下的配置文件
    $modeConfigFilePath = "$fileBase/Config." . Config::get("mode") . ".php";
    if (file_exists($modeConfigFilePath)) {
      Config::read($modeConfigFilePath);
    }

    //* 本地下的配置文件
    $localConfigFilePath = "$fileBase/Config.local.php";
    if (file_exists($localConfigFilePath)) {
      Config::read($localConfigFilePath);
    }
  }
}
