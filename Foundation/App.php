<?php

namespace kernel\Foundation;

use Exception as GlobalException;
use gstudio_kernel\Foundation\ReturnResult\ReturnList;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\Response\ResponsePagination;

/**
 * KERNEL标识符
 */
if (!defined("F_KERNEL")) {
  define("F_KERNEL", true);
}

class App
{
  protected $AppId = null;
  protected $KernelId = null;
  protected $uri = null; //* 请求的URI
  protected $globalMiddlware = []; //*全局中间件
  protected $router = null; //* 路由相关
  protected $request = null; //* 请求相关
  public $Route = null; //* 当前匹配到的路由
  private $startTime = null; //* 开始时间戳
  private function __clone()
  {
  }
  public function __get($name)
  {
    return $this->$name;
  }
  /**
   * 构造App
   *
   * @param string $AppId AppId，也是项目文件夹名称
   * @param string $KernelId 指定kernel所在文件夹名称，也是kernel的ID
   */
  function __construct($AppId, $KernelId = "kernel")
  {
    $this->startTime = Date::milliseconds();
    $this->AppId = $AppId;
    $this->KernelId = $KernelId;
    //* 定义常量
    $this->defineConstants();

    //* 初始化配置
    $this->initConfig();

    //* 全局状态存储
    $GLOBALS['_STORE'] = [];

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
    define("F_KERNEL_ROOT", dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $this->KernelId);

    /**
     * 当前运行的项目APPID，也是项目的文件夹名称。值取自实例化APP时传入的第一个参数
     */
    define("F_APP_ID", $this->AppId);
    /**
     * 当前运行的项目APP根目录，绝对路径
     */
    define("F_APP_ROOT", dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $this->AppId);
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
    $LocaRouteFiles = [];
    $KernelRoutesDir = File::genPath(F_KERNEL_ROOT, "Routes");
    if (is_dir($KernelRoutesDir)) {
      //* 载入kernel路由
      $KernelRouteFiles = File::recursionScanDir($KernelRoutesDir);
      if (count($KernelRouteFiles)) {
        $LocaRouteFiles = array_merge($LocaRouteFiles, $KernelRouteFiles);
      }
    }

    $AppRoutesDir = File::genPath(F_APP_ROOT, "Routes");
    if (is_dir($AppRoutesDir)) {
      //* 载入App的路由
      $AppRouteFiles = File::recursionScanDir($AppRoutesDir);
      if (count($AppRouteFiles)) {
        $LocaRouteFiles = array_merge($LocaRouteFiles, $AppRouteFiles);
      }
    }
    foreach ($LocaRouteFiles as $FileItem) {
      include_once($FileItem);
      Router::prefix(null);
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
    // $EM = new ExtensionsModel();
    // $enabledExtensions = $EM->where("enabled", 1)->getOne();
    // foreach ($enabledExtensions as $extensionItem) {
    //   $mainFilepath = File::genPath(F_APP_ROOT, $extensionItem['path'], "Main.php");
    //   if (!\file_exists($mainFilepath)) {
    //     Response::error(500, 500, $extensionItem['name'] . " 扩展文件已损坏，请重新安装");
    //   }
    //   $namespace = implode("\\", [F_APP_ID, "Extensions", $extensionItem['extension_id'], "Main"]);
    //   if (!\class_exists($namespace)) {
    //     Response::error(500, 500, $extensionItem['name'] . " 扩展文件已损坏，请重新安装");
    //   }
    //   new $namespace();
    // }
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
  private function executeMiddleware($Middlewares, Controller $Controller, \Closure $callback)
  {
    if (!count($Middlewares) === 0) return $callback();

    $middleware = array_shift($Middlewares);
    if (count($Middlewares) > 0) {
      $next = function () use ($Middlewares, $Controller, $callback) {
        return $this->executeMiddleware($Middlewares, $Controller, $callback);
      };
    } else {
      $next = $callback;
    }

    $params = $middleware['params'] ?: [];
    array_push($params, $next);

    if (is_callable($middleware['target'])) {
      $executedResponse = $middleware['target'](...$params);
    } else {
      $MInstance = new $middleware['target']($this->request, $Controller);
      $executedResponse = call_user_func_array([$MInstance, "handle"], $params);
    }

    return $executedResponse;
  }
  public function execureController($callTarget, $callParams, &$Controller)
  {
    try {
      $response = call_user_func_array($callTarget, $callParams);
    } catch (GlobalException $E) {
      if ($E instanceof Exception) {
        throw new Exception($E->getMessage(), $E->statusCode, $E->errorCode, $E->getTrace());
      } else {
        throw new Exception($E->getMessage(), 500, "500:ServerError", $E->getTrace());
      }
    }

    if (is_callable($Controller) || is_null($Controller)) {
      $Controller = new Controller($this->request);
    }

    if (!is_null($response)) {
      if ($response instanceof \kernel\Foundation\HTTP\Response) {
        $Controller->response = $response;
      }

      if (is_callable($response)) {
        $Controller->response->setData($response);
      } else {
        if ($response instanceof ReturnList) {
          $response = new ResponsePagination($this->request, $response->total(), $response->getData());
        }

        if (!($response instanceof \kernel\Foundation\HTTP\Response)) {
          $Controller->response->setData($response);
        }
      }
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
    $Controller = null;
    if (is_callable($Route['controller'])) {
      $callTarget = $Route['controller'];
    } else {
      $Controller = new $Route['controller']($this->request);
      $ControllerHandleMethodName = is_null($Route['controllerHandleMethodName']) ? 'data' : $Route['controllerHandleMethodName'];
      if (!method_exists($Controller, $ControllerHandleMethodName)) {
        throw new Exception("控制器缺少 $ControllerHandleMethodName 方法");
      }
      $callTarget = [
        $Controller,
        $ControllerHandleMethodName
      ];
    }
    $callParams = $Route['params'] ?: [];

    //* 执行中间件
    if (count($Middlewares)) {
      $app = $this;
      $this->executeMiddleware($Middlewares, $Controller, function () use ($app, $callTarget, $callParams, &$Controller) {
        $app->execureController($callTarget, $callParams, $Controller);

        return $Controller->response;
      });
    } else {
      $this->execureController($callTarget, $callParams, $Controller);
    }

    if (!$Controller->response->error) {
      $Controller->completed();
    }

    $endTime = Date::milliseconds();
    if ($this->request->ajax()) {
      $Controller->response->json();
      $Controller->response->addBody([
        "requiredTime" => $endTime - $this->startTime . "ms",
        "version" => Config::get("version")
      ]);
    }
    if (is_callable($Controller->response->getData())) {
      call_user_func_array($Controller->response->getData(), []);
    } else {
      $Controller->response->output();
    }
    exit;
  }
}
