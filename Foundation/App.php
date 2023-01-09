<?php

namespace kernel\Foundation;

use Error;
use Exception as GlobalException;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Output;
use kernel\Middleware\GlobalExtensionsMiddleware;
use kernel\Model\ExtensionsModel;

/**
 * KERNEL标识符
 */
define("F_KERNEL", true);

class App extends Application
{
  private $AppId = null;
  private $KernelId = null;
  protected $uri = null; //* 请求的URI
  protected $globalMiddlware = []; //*全局中间件
  protected $router = null; //* 路由相关
  protected $request = null; //* 请求相关
  public $Route = null; //* 当前匹配到的路由
  private function __clone()
  {
  }
  public function __get($name)
  {
    return $this->$name;
  }
  function __construct(string $AppId, string $KernelId = "kernel")
  {
    $this->AppId = $AppId;
    $this->KernelId = $KernelId;
    //* 定义常量
    $this->defineConstants();

    //* 初始化配置
    $this->initConfig();

    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\ExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Foundation\Exception\ExceptionHandler::handle", E_ALL);

    ErrorCode::load(File::genPath(F_KERNEL_ROOT, "ErrorCodes.php")); //* 加载错误码

    //* 载入路由
    $this->loadRoutes();

    $this->request = new Request();
  }
  /**
   * 初始化以及定义常量
   *
   * @return void
   */
  protected function defineConstants()
  {
    /**
     * 缓存动态KEY，主要用于静态文件
     */
    define("F_CACHE_KEY", time());
    /**
     * 根目录，绝对路径
     */
    define("F_ROOT", dirname(__DIR__, 2));
    /**
     * KERNEL的ID，默认是“kernel”，实例化App时传入的第二个参数便是该值。该常量也是kernel目录文件夹的名称
     */
    define("F_KERNEL_ID", $this->KernelId);
    /**
     * KERNEL的根目录
     */
    define("F_KERNEL_ROOT", dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "kernel");

    /**
     * 当前运行的项目APPID，也是项目的文件夹名称。值取自实例化APP时传入的第一个参数
     */
    define("F_APP_ID", $this->AppId);
    /**
     * 当前运行的项目APP根目录，绝对路径
     */
    define("F_APP_ROOT", dirname(__DIR__, 1));
    /**
     * 当前运行的项目Data目录，绝对路径
     */
    define("F_APP_DATA", F_APP_ROOT . DIRECTORY_SEPARATOR . "Data");

    $KernelRelativePath = "";
    $AppRelativePath = "";
    //* kernel和app的两个绝对路径对比，获取相对路径
    if (F_KERNEL_ROOT === F_APP_ROOT) {
      $KernelDirs = explode(DIRECTORY_SEPARATOR, F_KERNEL_ROOT);
      $AppRelativePath = $KernelRelativePath = $KernelDirs[array_key_last($KernelDirs)];
    } else {
      $KernelDirs = explode(DIRECTORY_SEPARATOR, F_KERNEL_ROOT);
      $AppDirs = explode(DIRECTORY_SEPARATOR, F_APP_ROOT);

      $kernelDiffStartIndex = 0;
      $appDiffStartIndex = 0;

      foreach ($KernelDirs as $Index => $Dir) {
        if (isset($AppDirs[$Index])) {
          $kernelDiffStartIndex = $Index;
          $appDiffStartIndex = $Index;
        } else {
          $kernelDiffStartIndex = $Index;
        }
      }

      $KernelRelativePath = $KernelDirs[$kernelDiffStartIndex];
      $AppRelativePath = $AppDirs[$appDiffStartIndex];
    }

    /**
     * 内核目录，相对路径
     */
    define("F_KERNEL_DIR", $KernelRelativePath);
    /**
     * APP目录，相对路径
     */
    define("F_APP_DIR", $AppRelativePath);

    //* 获取URL地址
    $url = "";
    if (strtolower($_SERVER['REQUEST_SCHEME']) === 'https' && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] === 'on') {
      $url .= "https://";
    } else {
      $url .= "http://";
    }
    $url .= $_SERVER['HTTP_HOST'] . ":" . $_SERVER['SERVER_PORT'];
    /**
     * APP的URL地址
     */
    define("F_BASE_URL", $url);
  }
  /**
   * 初始化配置
   *
   * @return void
   */
  protected function initConfig()
  {
    $ConfigFilesDir = File::genPath(F_APP_ROOT, "Configs");
    if (!is_dir($ConfigFilesDir)) return true;
    $ConfigFiles = File::scandir($ConfigFilesDir);

    if (in_array("Config.php", $ConfigFiles)) {
      Config::read(File::genPath($ConfigFilesDir, "Config.php"));
    }
    $CurrentMode = Config::get("mode");
    $WalkModes = [$CurrentMode];
    //* 有可能读取了配置文件后，改变了模式。例如默认配置文件是release模式，但是release里面的mode是production，也就是release基于production模式，就需要读取production模式的文件，合并配置
    //* 这里会循环寻找继承的配置文件。不管找到的配置文件mode是否还有继承，找到的配置文件mode在WalkModes里面，就会终止掉循环
    //* 因为找到了已经合并过的配置，说明到头，如果再去找就会出现死循环，default -> development -> local 如果这时local配置的mode是default 那default的mode又是development 那么就会进入到死循环，所以就需要终止。
    while (in_array($CurrentMode, $WalkModes)) {
      $CurrentMode = Config::get("mode");
      if (!isset($CurrentMode) || $CurrentMode === null) {
        break;
      }
      if (!defined("F_APP_MODE")) {
        define("F_APP_MODE", Config::get("mode"));
      }
      if (in_array("Config.$CurrentMode.php", $ConfigFiles)) {
        Config::read(File::genPath($ConfigFilesDir, "Config.$CurrentMode.php"));
      } else {
        break;
      }
    }
    if (!defined("F_APP_MODE")) {
      define("F_APP_MODE", Config::get("mode"));
    }

    return true;
  }
  /**
   * 加载路由
   *
   * @return void
   */
  protected function loadRoutes()
  {
    $KernelRoutesDir = File::genPath(F_KERNEL_ROOT, "Routes");
    if (is_dir($KernelRoutesDir)) {
      //* 载入kernel路由

      $KernelRouteFiles = File::scandir($KernelRoutesDir);
      if (count($KernelRouteFiles)) {
        foreach ($KernelRouteFiles as $FileItem) {
          if (is_dir(File::genPath($KernelRoutesDir, $FileItem))) {
            continue;
          }
          include_once(File::genPath($KernelRoutesDir, $FileItem));
        }
      }
    }

    $AppRoutesDir = File::genPath(F_APP_ROOT, "Routes");
    if (is_dir($AppRoutesDir)) {
      //* 载入App的路由

      $AppRouteFiles = File::scandir($AppRoutesDir);
      if (count($AppRouteFiles)) {
        foreach ($AppRouteFiles as $FileItem) {
          if (is_dir(File::genPath($AppRoutesDir, $FileItem))) {
            continue;
          }
          include_once(File::genPath($AppRoutesDir, $FileItem));
        }
      }
    }

    return true;
  }
  /**
   * 加载扩展
   * 获取已开启的扩展，然后访问扩展Main入口文件，实例化扩展类
   *
   * @return void
   */
  protected function loadExtensions()
  {
    $EM = new ExtensionsModel();
    $enabledExtensions = $EM->where("enabled", 1)->getOne();
    foreach ($enabledExtensions as $extensionItem) {
      $mainFilepath = File::genPath(F_APP_ROOT, $extensionItem['path'], "Main.php");
      if (!\file_exists($mainFilepath)) {
        Response::error(500, 500, $extensionItem['name'] . " 扩展文件已损坏，请重新安装");
      }
      $namespace = implode("\\", [F_APP_ID, "Extensions", $extensionItem['extension_id'], "Main"]);
      if (!\class_exists($namespace)) {
        Response::error(500, 500, $extensionItem['name'] . " 扩展文件已损坏，请重新安装");
      }
      new $namespace();
    }
  }
  /**
   * 设置中间件
   *
   * @param \Closure|object $classOrFun 中间件类或者函数
   * @param array $executeParams 执行中间件时传入的参数
   * @return void
   */
  function setMiddlware($classOrFun, $executeParams = null)
  {
    array_push($this->globalMiddlware, [
      "target" => $classOrFun,
      "params" => $executeParams
    ]);
  }
  private function executeMiddleware($Middlewares, \Closure $callback)
  {
    if (!count($Middlewares) === 0) return $callback();

    $middleware = array_shift($Middlewares);
    $params = $middleware['params'] ?: [];
    array_unshift($params, $this->request);
    if (count($Middlewares) === 0) {
      array_unshift($params, $callback);
    } else {
      array_unshift($params, function () use ($Middlewares, $callback) {
        return $this->executeMiddleware($Middlewares, $callback);
      });
    }

    if (is_callable($middleware['target'])) {
      return $middleware['target'](...$params);
    } else {
      $MInstance = new $middleware['target']($this->request);
      return $MInstance->handle(...$params);
    }
  }
  public function run()
  {
    header("Access-Control-Allow-Origin:*");
    header('Access-Control-Allow-Methods:*');
    header('Access-Control-Allow-Headers:*');
    header('Access-Control-Max-Age:86400');
    header('Access-Control-Allow-Credentials: true');

    //* 载入扩展
    if (Config::get("extensions")) {
      $this->loadExtensions();
      // $this->setMiddlware(kernel\Foundation\GlobalExtensionsMiddleware::class);
    }

    //* 路由
    $Route = Router::match($this->request);

    if (!$Route) {
      throw new Exception("路由不存在", 404, 404);
    }
    $this->request->Route = $Route;

    $Middlewares = $this->globalMiddlware ?: [];
    if (is_array($Route['middlewares']) && count($Route['middlewares'])) {
      foreach ($Route['middlewares'] as $RouteMiddleware) {
        array_push($Middlewares, [
          "target" => $RouteMiddleware,
          "params" => []
        ]);
      }
    }

    $callTarget = [];
    if (is_callable($Route['controller'])) {
      $callTarget = $Route['controller'];
    } else {
      $Controller = new $Route['controller']($this->request);
      $callTarget = [
        $Controller,
        "data"
      ];
    }
    $callParams = array_merge([$this->request], $Route['params'] ?: []);
    $callParams = array_values($callParams);
    $controllerExecutedResult = null;

    //* 执行中间件
    if (count($Middlewares)) {
      $controllerExecutedResult = $this->executeMiddleware($Middlewares, function () use ($callTarget, $callParams) {
        try {
          $response = call_user_func_array($callTarget, $callParams);
        } catch (GlobalException $E) {
          if ($E instanceof Exception) {
            $response = new Response(null, $E->statusCode, $E->errorCode, $E->getMessage(), $E->getTrace());
          }
        }

        return $response;
      });
    } else {
      try {
        $controllerExecutedResult = call_user_func_array($callTarget, $callParams);
      } catch (GlobalException $E) {
        if ($E instanceof Exception) {
          $controllerExecutedResult = new Response(null, $E->statusCode, $E->errorCode, $E->getMessage(), $E->getTrace());
        }
      }
    }
    if (!($controllerExecutedResult instanceof \kernel\Foundation\HTTP\Response)) {
      $controllerExecutedResult = new Response($controllerExecutedResult);
    }

    if ($Controller instanceof Controller) {
      $Controller->response = $controllerExecutedResult;
      $Controller->completed();
    }

    $controllerExecutedResult->output();
    exit;
  }
}
