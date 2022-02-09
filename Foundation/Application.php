<?php

namespace kernel\Foundation;

use kernel\Model\ExtensionsModel;

class Application
{
  protected $uri = null; //* 请求的URI
  protected $globalMiddlware = []; //*全局中间件
  protected $router = null; //* 路由相关
  protected $request = null; //* 请求相关
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
  function setMiddlware($middlwareNameOfFunction)
  {
    array_push($this->globalMiddlware, $middlwareNameOfFunction);
  }
  protected function executiveController()
  {
    $controller = $this->router['controller'];
    if (\is_callable($controller)) {
      return $controller($this->request);
    } else {
      $instance = new $controller($this->request);
      $result = $instance->data($this->request);
      if ($this->request->ajax() === NULL) {
        View::outputFooter();
      } else {
        if (gettype($instance->serialization) === "string" || (is_array($instance->serialization) && count($instance->serialization) > 0)) {
          if (gettype($instance->serialization) === "array") {
            $ruleName = "serializer_" . time();
            Serializer::addRule($ruleName, $instance->serialization);
            $instance->serialization = $ruleName;
          }
          $result = Serializer::use($instance->serialization, $result);
        }
      }
      return $result;
    }
  }
  protected function executiveMiddleware()
  {
    $middlewares = array_reverse($this->globalMiddlware);
    if (isset($this->router['middleware']) && !empty($this->router['middleware'])) {
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
      if (\is_callable($middlewareItem)) {
        $middlewareItem(function () use (&$executeCount) {
          $executeCount++;
        }, $this->request);
      } else {
        $middlewareInstance = new $middlewareItem();
        $isNext = false;
        $middlewareInstance->handle(function () use (&$isNext) {
          $isNext = true;
        }, $this->request);
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
   * 载入扩展
   * 获取已开启的扩展，然后访问扩展Main入口文件，执行handle方法
   *
   * @return void
   */
  protected function loadExtensions()
  {
    $EM = new ExtensionsModel();
    $enabledExtensions = $EM->where("enabled", 1)->getOne();
    foreach ($enabledExtensions as $extensionItem) {
      $mainFilepath = F_ROOT . $extensionItem['path'] . "/Main.php";
      if (!\file_exists($mainFilepath)) {
        Response::error(500, 500, $extensionItem['name'] . " 扩展文件已损坏，请重新安装");
      }
      $namespace = "\\" . $extensionItem['plugin_id'] . "\\Extensions\\" . $extensionItem['extension_id'] . "\\Main";
      if (!\class_exists($namespace)) {
        Response::error(500, 500, $extensionItem['name'] . " 扩展文件已损坏，请重新安装");
      }
      $MainInstance = new $namespace();
      $MainInstance->handle();
    }
  }
  protected static function initGlobalVariables($appId)
  {
    //* 存放全局用到的数据
    $GlobalVariables = [
      "id" => $appId, //* 当前运行中的应用ID
      "sets" => [], //* 设置项，包含配置项里设置的全局设置项
      "rewriteURL" => [], //* 重写的URL
      "mode" => Config::get("mode", $appId), //* 当前运行模式
      "langs" => [], //* 字典
      "kernel" => [ //* 内核
        "root" => F_KERNEL_ROOT,
        "assets" => F_BASE_URL . "/kernel/Assets",
        "views" => F_BASE_URL . "/kernel/Views",
      ],
      "$appId" => [
        "root" => F_ROOT . "/$appId",
        "assets" => F_ROOT . "/$appId/Assets",
        "views" => F_ROOT . "/$appId/Views",
      ]
    ];
    GlobalVariables::set([
      "_GG" => $GlobalVariables
    ]);
  }
}
