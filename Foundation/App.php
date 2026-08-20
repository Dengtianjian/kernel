<?php

namespace kernel\Foundation;

use Exception as GlobalException;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\Date;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Exception\RuyiException;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\Lifecycle;
use kernel\Foundation\Middleware\Middleware;

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
  /**
   * 中间件
   *
   * @var Middleware
   */
  protected $middleware = null;
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
  /**
   * ensureInstances() 进行中标记（防止 Lifecycle 构造触发钩子回调时递归）
   *
   * @var bool
   */
  protected static $ensuringInstances = false;
  protected function __clone() {}
  /**
   * 构建 App
   * @param string $id 设定一个 APP 唯一 ID，跟项目目录同名
   * @param string $kernelId 修改内核默认id。内核也是一个 APP，所有也有ID，默认是 kernel
   *
   * 构造不实例化任何组件（延迟实例化）：Router / Request / Middleware / Lifecycle / Config
   * 由 setup() 手动注入（自定义实例），或在 run()/handle() 时由 ensureInstances() 兜底实例化；
   * 中间件注册、生命周期钩子请在 Setup 装配类中手动 new Middleware / new Lifecycle 后
   * 调用实例方法设置（$middleware->set(...)、$lifeCycle->onBootUp(...) 等）再注入 App；
   * Cache / FileSystem 等需要时也请在 Setup 装配类中手动实例化。
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

    include_once(FileHelper::combinedFilePath(Path::kernelRoot() . "/Foundation/Common.php"));

    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\ExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Foundation\Exception\ExceptionHandler::handle", E_ALL);
  }
  /**
   * 应用装配：手动实例化组件（配置、缓存、文件系统、自定义 Router/Request 等）
   *
   * 必须在 run() 之前调用，调用时立即执行传入的参数：
   * - 传类名（字符串）：立即构建该类，构造参数为当前 App 实例（new $call($this)，构造即装配），
   *   Bootstrap 构造签名 __construct($app)。Bootstrap 类内手动 new Config / new Cache / new FileSystem；
   *   new Middleware 后 ->set(...) 注册中间件、new Lifecycle 后 ->onBootUp(...) / ->onShutdown(...)
   *   注册钩子，再通过 $app->set([...]) 批量注入自定义实例：
   *     $app->set(["router" => $router, "lifeCycle" => $lifeCycle, "middleware" => $middleware]);
   * - 传闭包：立即执行，参数为当前 App 实例，同样可调用 $app->set([...]) 注入自定义实例。
   *
   * run() 时若 Router / Request / Middleware / Lifecycle / Config 仍未注入，框架自动兜底实例化。
   *
   * @param string|callable $call 装配类名（构造接收 $app）或装配闭包
   * @return $this
   */
  public function setup($call)
  {
    if (is_string($call)) {
      //* 类名：立即构建（构造即装配），传入当前 App 实例；无参构造（旧式 Bootstrap）兼容
      if (class_exists($call)) {
        $constructor = (new \ReflectionClass($call))->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
          new $call();
        } else {
          new $call($this);
        }
      }
      return $this;
    }
    if (is_callable($call)) {
      //* 闭包：立即执行，注入当前 App 实例
      $call($this);
    }
    return $this;
  }
  /**
   * 延迟实例化兜底：setup() 未注入时，run()/handle() 前由框架自动实例化
   *
   * Router / Request / Middleware / Lifecycle 未实例化则构造默认实例；
   * Config 未加载（未 new Config）则加载当前应用配置。
   * Cache / FileSystem 等不在此列：需要时请在 Setup 装配类中手动实例化。
   *
   * @return void
   */
  protected function ensureInstances()
  {
    if (self::$ensuringInstances) {
      return;
    }
    self::$ensuringInstances = true;
    try {
      //* 初始化配置（未加载时加载 Configs/ 目录配置）
      if (!Config::loaded()) {
        new Config;
      }
      //* Router：构造内按模式加载路由（http 模式注册 URI 路由；command 模式注册 CLI 命令）
      if ($this->router === null) {
        $this->router = new Router();
      }
      //* Request：HTTP 与 CLI 都实例化（CLI 下 URI 由 Console::handle() 置为命中的命令名）
      if ($this->request === null) {
        $this->request = new Request();
      }
      //* 中间件管理器
      if ($this->middleware === null) {
        $this->middleware = new Middleware();
      }
      //* 生命周期管理器（先于 Request/Middleware 已就绪，Lifecycle 构造会触发 beforeCreate/afterCreate 钩子）
      if ($this->lifeCycle === null) {
        $this->lifeCycle = new Lifecycle();
      }
    } finally {
      self::$ensuringInstances = false;
    }
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
   * 批量注入自定义组件实例（setup() 装配中使用；未注入的组件 run() 时自动兜底实例化）
   *
   * 数组键为组件名，值为手动 new 出来的实例，用于替换 App 的对应组件：
   *   $app->set([
   *     "router" => new Router,
   *     "request" => $request,
   *     "lifeCycle" => $lifeCycle,
   *     "middleware" => $middleware
   *   ]);
   * 支持键：router / request / lifeCycle / middleware（其余键忽略）。
   *
   * @param array $instances 组件名 => 实例
   * @return $this
   */
  public function set(array $instances)
  {
    foreach (["router", "request", "lifeCycle", "middleware"] as $name) {
      if (array_key_exists($name, $instances)) {
        $this->{$name} = $instances[$name];
      }
    }

    return $this;
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
        if (!($response instanceof \kernel\Foundation\HTTP\Response)) {
          $controller->response->setData($response);
        }
      }
    }
  }

  public function run()
  {
    //* 延迟实例化兜底：setup() 未注入的组件在此自动实例化
    $this->ensureInstances();

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
        //* 执行中间件（全局中间件 + 路由级中间件）
        $app = $this;
        $middlewareExecutedResult = $this->middleware->execute($route['middlewares'], $controller, function () use ($app, $callTarget, $callParams, &$controller) {
          $app->executeController($callTarget, $callParams, $controller);

          return $controller->response;
        });
        if ($middlewareExecutedResult->error) {
          $controller->response = $middlewareExecutedResult;
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
   * 获取请求实例
   *
   * @return Request
   */
  public function request()
  {
    if ($this->request === null) {
      $this->request = new Request();
    }
    return $this->request;
  }
  public function router()
  {
    if ($this->router === null) {
      $this->router = new Router();
    }
    return $this->router;
  }
  public function lifeCycle()
  {
    if ($this->lifeCycle === null) {
      $this->lifeCycle = new Lifecycle();
    }
    return $this->lifeCycle;
  }
  public function middleware()
  {
    if ($this->middleware === null) {
      $this->middleware = new Middleware();
    }
    return $this->middleware;
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
