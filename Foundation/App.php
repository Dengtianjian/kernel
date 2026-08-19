<?php

namespace kernel\Foundation;

use Exception as GlobalException;
use kernel\Foundation\ReturnResult\ReturnList;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\Date;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Exception\RuyiException;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\HTTP\Response\ResponsePagination;
use kernel\Foundation\Lifecycle;

class App
{
  /**
   * 当前应用的ID（id），也是项目文件夹名称。取自已实例化 App 的第一个构造参数，后实例化者覆盖
   *
   * @var string|null
   */
  protected $id = null;
  /**
   * 内核的ID（KernelId），也是内核目录文件夹名称，默认 "kernel"
   *
   * @var string|null
   */
  protected $kernelId = null;
  protected $globalMiddlware = []; //*全局中间件
  protected $router = null; //* 路由相关
  protected $request = null; //* 请求相关
  protected $startTime = null; //* 开始时间戳
  protected $lifeCycle = null; //* 生命周期管理器（Lifecycle），委托管理引导/结束/错误钩子
  /**
   * 当前（最近实例化）的 App 实例
   *
   * 实例化 App 时自动注册（构造即注册），getApp() / App::getInstance() 从该存储获取当前实例。
   *
   * @var App|null
   */
  protected static $currentApp = null;
  protected function __clone() {}
  /**
   * 构建 App
   * @param string $id 设定一个 APP 唯一 ID，跟项目目录同名
   * @param string $kernelId 修改内核默认id。内核也是一个 APP，所有也有ID，默认是 kernel
   *
   * 构造时实例化 Router（构造内按模式加载路由：http 模式注册 URI 路由、command 模式注册 CLI 命令，
   * 模式自动判断：非 cli 环境为 http，否则 command）并始终实例化 Request。
   * CLI 下 Request 的 URI 由 Console::handle() 置为命中的命令名；HTTP 下为请求 URI。
   */
  function __construct($id, $kernelId = "kernel")
  {
    //* 注册当前 App 实例：getApp() / App::getInstance() 返回该实例（后实例化者覆盖前者）
    self::$currentApp = $this;

    $this->id = $id;
    $this->kernelId = $kernelId;

    $this->startTime = Date::milliseconds();

    //* 定义常量
    $this->defineConstants();

    //* 实例化 FileSystem（无参构造，不计算路径；路径在每次静态方法调用时自动计算）
    new FileSystem;

    //* 初始化配置
    new Config;

    //* 实例化 Cache（构造时生成 16 位随机 KEY，Cache::key() 读取）
    new Cache;

    include_once(FileHelper::combinedFilePath(FileSystem::kernelRoot() . "/Foundation/Common.php"));

    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\ExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Foundation\Exception\ExceptionHandler::handle", E_ALL);

    //* 实例化 Router：构造内加载路由（http 模式注册 URI 路由；command 模式注册 CLI 命令）
    $this->router = new Router();

    //* 请求实例：HTTP 与 CLI 都实例化（CLI 下 URI 由 Console::handle() 置为命中的命令名）
    $this->request = new Request();

    //* 生命周期管理器：委托管理引导/结束/错误钩子（注入当前 Request，钩子与装配类触发时使用）
    $this->lifeCycle = new Lifecycle(self::id(), $this->request);
  }
  /**
   * 初始化以及定义常量
   *
   * @return void
   */
  protected function defineConstants()
  {
    //* 获取URL地址
    $url = "";

    if (array_key_exists("REQUEST_SCHEME", $_SERVER)) {
      if (array_key_exists("HTTPS", $_SERVER) && $_SERVER['HTTPS'] === 'on') {
        $url .= "https://";
      } else {
        $url .= "http://";
      }

      if (array_key_exists("HTTP_HOST", $_SERVER)) {
        $url .= $_SERVER['HTTP_HOST'];
      }
    }
    /**
     * APP的URL地址
     */
    define("F_BASE_URL", $url);
  }
  /**
   * 注册应用引导装配类（Lifecycle\Bootup），或注册启动钩子
   *
   * 双语义方法：
   * - 传字符串（类名）或不传：注册启动装配类——由 run() 在请求到达后、路由匹配前
   *   实例化（构造即装配，构造参数为当前请求 $request；CLI 下同样收到 Request 实例，其 URI 为命中的命令名），可立即执行
   *   注册事件（new Event(...)）、按需引入 Lifecycle/ 下其它文件（events.php 等）、
   *   初始化数据库连接等；只注册一次（幂等），类不存在时静默跳过。
   * - 传回调（闭包/数组等）：注册启动钩子。
   *
   * 引导装配与关闭装配分离：关闭相关代码请放在 Lifecycle\Shutdown 中，通过 onShutdown() 注册。
   * 不传类名时默认使用 App::id()\Lifecycle\Bootup（应用命名空间下的 Lifecycle 目录引导类）。
   * HTTP 与 CLI（Console）入口都应在 run()/handle() 之前调用，例如入口文件中
   * $app->onBootUp(\myapp\Lifecycle\Bootup::class)。
   *
   * @param string|\Closure|array|null $callback 应用引导装配类名（如 \myapp\Lifecycle\Bootup::class），
   *                                             或启动钩子回调；不传时默认加载 App::id()\Lifecycle\Bootup
   * @return $this
   */
  public function onBootUp($callback = null)
  {
    $this->lifeCycle->onBootUp($callback);

    return $this;
  }
  /**
   * 设置中间件
   *
   * @param \Closure|object|string $classOrFun 中间件类或者函数
   * @param array $executeParams 执行中间件时传入的参数
   * @return void
   */
  function setMiddleware($classOrFun, $executeParams = null)
  {
    array_push($this->globalMiddlware, [
      "target" => $classOrFun,
      "params" => $executeParams
    ]);
  }
  private function executeMiddleware($middlewares, Controller $controller, \Closure $callback)
  {
    if (count($middlewares) === 0)
      return $callback();

    $middleware = array_shift($middlewares);
    $next = function () use ($middlewares, $controller, $callback) {
      return $this->executeMiddleware($middlewares, $controller, $callback);
    };

    $params = $middleware['params'] ?: [];
    array_push($params, $next);

    if (is_string($middleware['target'])) {
      $middlewareInstance = new $middleware['target']($this->request, $controller);
      $executedResponse = $middlewareInstance->handle(...$params);
    } else {
      array_unshift($params, $this->request);
      $executedResponse = $middleware['target'](...$params);
    }

    if ($executedResponse === null) {
      throw new \RuntimeException(sprintf(
        'Middleware [%s]::handle() 未返回 Response，可能缺少 return $next()',
        is_string($middleware['target']) ? $middleware['target'] : 'Closure'
      ));
    }

    return $executedResponse;
  }
  public function executeController($callTarget, $callParams, &$controller)
  {
    try {
      $response = call_user_func_array($callTarget, array_values($callParams));
    } catch (GlobalException $e) {
      if ($e instanceof RuyiException) {
        throw new RuyiException($e->getMessage(), $e->statusCode, $e->errorCode, $e->errorDetails ?: $e->getTrace());
      } else {
        throw new RuyiException($e->getMessage(), 500, "500:ServerError", $e->getTrace());
      }
    }

    if (is_callable($controller) || is_null($controller)) {
      $controller = new Controller($this->request);
    }

    if (!is_null($response)) {
      if ($response instanceof \kernel\Foundation\HTTP\Response) {
        $controller->response = $response;
      }

      if (is_callable($response)) {
        $controller->response->setData($response);
      } else {
        if ($response instanceof ReturnList) {
          $controller->response = new ResponsePagination($this->request, $response->total(), $response->getData());
        }

        if (!($response instanceof \kernel\Foundation\HTTP\Response)) {
          $controller->response->setData($response);
        }
      }
    }
  }

  /**
   * 注册应用关闭装配类（Lifecycle\Shutdown），或注册关闭钩子
   *
   * 双语义方法：
   * - 传字符串（类名）：注册关闭装配类——由 run() 在请求结束（正常或异常）时实例化
   *   （构造即装配，构造参数为 $controller->response；CLI 下传命令退出码），可立即执行
   *   记录日志、释放资源等；只注册一次（幂等），类不存在时静默跳过。
   * - 传回调（闭包/数组等）：注册关闭钩子。可在控制器或任意业务代码中动态注册
   *   （Controller::onShutdown() 便捷方法），回调签名 function ($response, $context = null)：
   *   - $response：当前响应对象（异常路径可能为 null；CLI 下为命令退出码）
   *   - $context["exception"]：非空表示异常结束（可通过 App::exception() 读取）
   *   - $context["error"]：是否异常结束
   *   关闭钩子无论请求正常结束还是异常结束都会执行（异常必达）。
   *
   * @param callable|string $callback 回调函数，或关闭装配类名（如 \myapp\Lifecycle\Shutdown::class）
   * @return $this
   */
  public function onShutdown($callback)
  {
    $this->lifeCycle->onShutdown($callback);

    return $this;
  }
  /**
   * 注册错误钩子（请求处理过程中捕获到异常时触发）
   *
   * 仿 Vue 3 的 onErrorCaptured：异常路径下，框架捕获异常后会先触发错误钩子（onError），
   * 再触发结束钩子（onShutdown），最后交由全局异常处理器输出。可用于统一上报监控、
   * 记录错误日志等，与结束钩子分工（onError 管错误处理，onShutdown 管资源释放/步骤记录）。
   * 可叠加、可重复注册，按注册顺序依次执行；同一异常只触发一次。
   *
   * @param callable $callback 错误钩子回调，function (\Throwable $exception)
   * @return $this
   */
  public function onError($callback)
  {
    $this->lifeCycle->onError($callback);

    return $this;
  }
  public function run()
  {
    if ($this->request()->method === "options") {
      //* 预检请求也执行结束钩子，保证生命周期有始有终（记录日志、释放资源等）
      $this->lifeCycle->fireShutdown(null, [
        "exception" => null,
        "error" => false,
        "preflight" => true
      ]);
      return;
    }

    //* 载入扩展 ~ 输出：正常与异常结束都会执行结束钩子（异常交由全局异常处理器输出）
    try {
      //* 调用生命周期"启动"钩子
      $this->lifeCycle->fireBootUp();

      //* 路由
      $route = Router::match($this->request);

      if (!$route) {
        throw new Exception("路由不存在", 404, 404, [
          "uri" => $this->request()->URI,
          'method' => $this->request()->method
        ]);
      }
      $this->request->Route = $route;
      $this->request->params->set($route['params']);

      $middlewares = $this->globalMiddlware ?: [];
      if (is_array($route['middlewares']) && count($route['middlewares'])) {
        foreach ($route['middlewares'] as $routeMiddleware) {
          array_push($middlewares, [
            "target" => $routeMiddleware,
            "params" => []
          ]);
        }
      }

      $callTarget = [];
      $callParams = $route['params'] ?: [];
      $routeInstantiateParams = $route['controllerInstantiateParams'];
      $controller = null;
      if (is_callable($route['controller'])) {
        $callTarget = $route['controller'];
        array_unshift($callParams, $this->request, ...$routeInstantiateParams);
      } else {
        $controller = new $route['controller']($this->request, ...$routeInstantiateParams);
        $controller->before();
        $controllerHandleMethodName = is_null($route['controllerHandleMethodName']) ? 'data' : $route['controllerHandleMethodName'];
        if (!method_exists($controller, $controllerHandleMethodName)) {
          throw new Exception("控制器缺少 $controllerHandleMethodName 方法");
        }
        $callTarget = [
          $controller,
          $controllerHandleMethodName
        ];
      }

      if (!$controller->response->error) {
        //* 执行中间件
        if (count($middlewares)) {
          $app = $this;
          $middlewareExecutedResult = $this->executeMiddleware($middlewares, $controller, function () use ($app, $callTarget, $callParams, &$controller) {
            $app->executeController($callTarget, $callParams, $controller);

            return $controller->response;
          });
          if ($middlewareExecutedResult->error) {
            $controller->response = $middlewareExecutedResult;
          }
        } else {
          $this->executeController($callTarget, $callParams, $controller);
        }
      }

      $controller->after();

      $endTime = Date::milliseconds();
      if ($this->request->ajax() && !$controller->response->OutputType()) {
        $controller->response->json();
        $controller->response->addBody([
          "requiredTime" => $endTime - $this->startTime . "ms"
        ]);
      }

      //* 调用生命周期"结束"钩子
      $this->lifeCycle->fireShutdown($controller->response, [
        "exception" => null,
        "error" => false
      ]);

      if (is_callable($controller->response->getData())) {
        call_user_func_array($controller->response->getData(), []);
      } else {
        $controller->response->output();
      }
      exit;
    } catch (\Throwable $e) {
      //* 异常路径：先触发错误钩子（onError，统一上报/记录），再执行结束钩子（记录已执行到的步骤、释放资源），最后交由全局异常处理器输出
      $this->lifeCycle->fireError($e);
      $this->lifeCycle->fireShutdown(isset($controller) ? $controller->response : null, [
        "exception" => $e,
        "error" => true
      ]);
      throw $e;
    }
  }
  /**
   * 获取当前请求处理过程中捕获的异常
   *
   * 正常结束返回 null；控制器/中间件/引导等任意环节抛出并被框架捕获后，
   * shutdown 回调可通过该方法判断是否异常结束、读取错误信息
   * （例如外部 SDK 调用失败时记录已执行到的步骤）。
   *
   * @return \Throwable|null
   */
  public function exception()
  {
    return $this->lifeCycle->exception();
  }
  /**
   * 获取请求实例
   *
   * @return Request
   */
  public function request()
  {
    return $this->request;
  }
  /**
   * 获取当前（最近实例化）的 App 实例
   *
   * 实例化 App（new App($id)）时即自动注册到静态存储（后实例化者覆盖前者）。
   * 全局函数 getApp() 等价于本方法。
   *
   * @return App|null 尚未实例化任何 App 时返回 null
   */
  public static function getInstance()
  {
    return self::$currentApp;
  }
  /**
   * 获取当前应用的ID（id），即项目文件夹名称
   *
   * 取自已实例化 App 的第一个构造参数（后实例化者覆盖）。
   *
   * @return string|null 尚未实例化任何 App 时返回 null
   */
  public static function id(): ?string
  {
    return self::$currentApp === null ? null : self::$currentApp->id;
  }
  /**
   * 获取内核的ID（KernelId），即内核目录文件夹名称，默认 "kernel"
   *
   * 取自已实例化 App 的第二个构造参数（后实例化者覆盖）。
   *
   * @return string|null 尚未实例化任何 App 时返回 null
   */
  public static function kernelId(): ?string
  {
    return self::$currentApp === null ? null : self::$currentApp->kernelId;
  }
  /**
   * 获取当前运行模式（mode）
   *
   * 读取配置的 "mode" 键（Config::get("mode", "production")），未配置或未加载配置时默认 "production"。
   * 替代原 F_APP_MODE 常量，无需提前定义，随时可安全调用。
   *
   * @return string 运行模式（production / development / local / release 等）
   */
  public static function mode(): string
  {
    return Config::get("mode", "production");
  }
}
