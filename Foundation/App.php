<?php

namespace kernel\Foundation;

use Exception as GlobalException;
use kernel\Foundation\Application\Applications;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\Date;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\HTTP\Response\ServerSentEvent;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\Lifecycle;
use kernel\Foundation\Middleware\Middleware;
use kernel\Foundation\Module\Modules;
use kernel\Foundation\Router\Router;
use kernel\Foundation\URL;

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
  /**
   * 模块管理器（Modules），承载已装载模块的启动/停止与检索
   *
   * 延迟实例化：setup() 未注入时由 ensureInstances() / modules() 自动 new 默认实例。
   * 装配时手动 new Modules() 后通过 $app->set(["modules" => $modules]) 注入自定义实例。
   *
   * @var Modules|null
   */
  protected $modules = null;
  /**
   * 请求实例（Request），承载当前请求的 URI / 方法 / 参数 / Route 等
   *
   * 延迟实例化：setup() 未注入时由 ensureInstances() / request() 自动 new 默认实例。
   * CLI 下 URI 由 Console::handle() 置为命中的命令名；HTTP 下为请求 URI。
   *
   * @var Request|null
   */
  protected $request = null;
  /**
   * 路由实例（Router），承载路由表、命令表及路由匹配
   *
   * 延迟实例化：setup() 未注入时由 ensureInstances() / router() 自动 new 默认实例。
   * 匹配所需的当前请求通过 getApp()->request() 获取，构造时无需传参。
   *
   * @var Router|null
   */
  protected $router = null;
  /**
   * 生命周期管理器（Lifecycle），委托管理引导/结束/错误钩子
   *
   * 延迟实例化：setup() 未注入时由 ensureInstances() / lifeCycle() 自动 new 默认实例。
   * 装配时手动 new Lifecycle 后调用实例方法 onBootUp/onShutdown/onError 注册钩子。
   *
   * @var Lifecycle|null
   */
  protected $lifeCycle = null;
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
   * 构造不实例化任何组件（延迟实例化）：Request / Middleware / Lifecycle / Config
   * 由 setup() 手动注入（自定义实例），或在 run()/handle() 时由 ensureInstances() 兜底实例化；
   * 中间件注册、生命周期钩子请在 Setup 装配类中手动 new Middleware / new Lifecycle 后
   * 调用实例方法设置（$middleware->set(...)、$lifeCycle->onBootUp(...) 等）再注入 App；
   * Cache / FileSystem 等需要时也请在 Setup 装配类中手动实例化。
   * 路由由 App 实例化并持有（ensureInstances()/router() 内 new Router 加载路由表），
   * 匹配所需的当前请求经 getApp()->request() 获取。
   * CLI 下 Request 的 URI 由 Console::handle() 置为命中的命令名；HTTP 下为请求 URI。
   */
  function __construct($id, $kernelId = "kernel")
  {
    $this->boot($id, $kernelId);

    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\ExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Foundation\Exception\ExceptionHandler::handle", E_ALL);
  }
  public function boot($id, $kernelId = "kernel")
  {
    //* 登记到应用管理器：首个注册者自动成为默认应用
    Applications::register($this, $id);

    include_once(Path::join(Path::kernel() . "/Foundation/Common.php"));

    $this->id = $id;
    $this->kernelId = $kernelId;
    Config::set([
      "startTime" => Date::milliseconds()
    ]);
  }

  /**
   * 应用装配：手动实例化组件（配置、缓存、文件系统、自定义 Request 等）
   *
   * 必须在 run() 之前调用，调用时立即执行传入的参数：
   * - 传类名（字符串）：立即构建该类，构造参数为当前 App 实例（new $call($this)，构造即装配），
   *   Bootstrap 构造签名 __construct($app)。Bootstrap 类内手动 new Config / new Cache / new FileSystem；
   *   new Middleware 后 ->set(...) 注册中间件、new Lifecycle 后 ->onBootUp(...) / ->onShutdown(...)
   *   注册钩子，再通过 $app->set([...]) 批量注入自定义实例：
   *     $app->set(["lifeCycle" => $lifeCycle, "middleware" => $middleware]);
   * - 传闭包：立即执行，参数为当前 App 实例，同样可调用 $app->set([...]) 注入自定义实例。
   *
   * run() 时若 Request / Router / Middleware / Lifecycle / Config 仍未注入，框架自动兜底实例化；
   * 路由由 App 实例化并持有（new Router），匹配所需的请求经 getApp()->request() 获取。
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
   * Request / Router / Middleware / Lifecycle 未实例化则构造默认实例；
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
      //* Request：HTTP 与 CLI 都实例化（CLI 下 URI 由 Console::handle() 置为命中的命令名）
      if ($this->request === null) {
        $this->request = new Request();
      }
      //* Router：由 App 持有并实例化（加载路由表），匹配时经 getApp()->request() 获取当前请求
      if ($this->router === null) {
        $this->router = new Router();
      }
      //* 中间件管理器
      if ($this->middleware === null) {
        $this->middleware = new Middleware();
      }
      //* 模块管理器
      if ($this->modules === null) {
        $this->modules = new Modules();
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
   * 批量注入自定义组件实例（setup() 装配中使用；未注入的组件 run() 时自动兜底实例化）
   *
   * 数组键为组件名，值为手动 new 出来的实例，用于替换 App 的对应组件：
   *   $app->set([
   *     "request" => $request,
   *     "router" => $router,
   *     "lifeCycle" => $lifeCycle,
   *     "middleware" => $middleware,
   *     "modules" => $modules
   *   ]);
   * 支持键：request / router / lifeCycle / middleware / modules（其余键忽略）。
   * 路由由 App 实例化并持有（也可经 set() 注入自定义实例），匹配时经 getApp()->request() 获取当前请求。
   *
   * @param array $instances 组件名 => 实例
   * @return $this
   */
  public function set(array $instances)
  {
    foreach (["request", "router", "lifeCycle", "middleware", "modules"] as $name) {
      if (array_key_exists($name, $instances)) {
        $this->{$name} = $instances[$name];
      }
    }

    return $this;
  }
  /**
   * 执行控制器/闭包路由，并将返回值写入控制器响应
   *
   * 由 run() 中的中间件链回调调用。核心逻辑：
   * - 以 call_user_func_array 调用路由目标（闭包或控制器方法），捕获异常并包装为 Error；
   * - $controller 为 null 或可调用时（闭包路由），补建默认 Controller 以承载响应；
   * - 将返回的响应写入 $controller->response：Response 实例直接赋值，否则 setData()。
   *
   * @param callable|array $callTarget 路由目标（闭包，或 [控制器, 方法名]）
   * @param array $callParams 传给路由目标的参数
   * @param object|null $controller 控制器实例（按引用传入；闭包路由时为 null，此处补建）
   * @return void
   */
  /**
   * 按参数名绑定路由参数（反射）
   *
   * 遍历方法/闭包的参数列表，按参数名从 $routeParams 中取值。
   * - 参数在 $routeParams 中存在：使用路由参数值
   * - 参数有默认值且 $routeParams 中不存在：使用默认值
   * - 参数必选且缺失：抛 InvalidArgumentException
   *
   * @param \ReflectionParameter[] $parameters 方法/闭包的参数列表
   * @param array $routeParams 路由参数（参数名 => 值）
   * @return array 按参数顺序排列的值数组
   */
  protected function bindParamsByName($parameters, $routeParams)
  {
    $bound = [];
    foreach ($parameters as $param) {
      $name = $param->getName();
      if (array_key_exists($name, $routeParams)) {
        $bound[] = $routeParams[$name];
      } elseif ($param->isDefaultValueAvailable()) {
        $bound[] = $param->getDefaultValue();
      } else {
        throw new \InvalidArgumentException(
          "控制器方法缺少必选参数 \"{$name}\"，无法按名绑定。"
        );
      }
    }
    return $bound;
  }

  /**
   * 按参数名绑定闭包路由参数（反射，保持声明顺序）
   *
   * 与 bindParamsByName() 的差异：闭包参数支持框架注入与隐式参数，逐个参数按其声明位置绑定：
   * 1. 参数名为 request 或类型声明为 Request 派生类型：注入当前请求实例
   * 2. 参数名存在于路由参数（URL 路由参数 + append 隐式参数）：取对应值
   * 3. 参数名存在于路由实例化参数（->parameters() 关联数组）：取对应值
   * 4. 路由实例化参数中的顺序值（数字下标）：依次补位
   * 5. 参数有默认值：使用默认值
   * 6. 参数必选且缺失：抛 InvalidArgumentException
   *
   * @param \ReflectionParameter[] $parameters 闭包参数列表
   * @param array $routeParams 路由参数（参数名 => 值，含 append 隐式参数）
   * @param array $instantiateParams 路由实例化参数（->parameters()）
   * @param Request $request 当前请求实例
   * @return array 按参数顺序排列的值数组
   */
  protected function bindClosureParams($parameters, $routeParams, $instantiateParams, $request)
  {
    //* 数字下标的实例化参数按声明顺序补位
    $orderedExtras = [];
    foreach ((array) $instantiateParams as $key => $value) {
      if (is_int($key)) {
        $orderedExtras[] = $value;
      }
    }

    $bound = [];
    foreach ($parameters as $param) {
      $name = $param->getName();
      if ($param->isVariadic()) {
        break;
      }
      if ($name === "request" || $this->isRequestParameter($param, $request)) {
        $bound[] = $request;
      } elseif (array_key_exists($name, $routeParams)) {
        $bound[] = $routeParams[$name];
      } elseif (is_array($instantiateParams) && array_key_exists($name, $instantiateParams)) {
        $bound[] = $instantiateParams[$name];
      } elseif (count($orderedExtras)) {
        $bound[] = array_shift($orderedExtras);
      } elseif ($param->isDefaultValueAvailable()) {
        $bound[] = $param->getDefaultValue();
      } else {
        throw new \InvalidArgumentException(
          "闭包路由缺少必选参数 \"{$name}\"，无法按名绑定。"
        );
      }
    }

    return $bound;
  }

  /**
   * 判断闭包参数是否应注入当前请求实例（按类型声明）
   *
   * @param \ReflectionParameter $param 闭包参数
   * @param Request $request 当前请求实例
   * @return bool
   */
  protected function isRequestParameter($param, $request)
  {
    $type = $param->getType();
    if (!($type instanceof \ReflectionNamedType) || $type->isBuiltin()) {
      return false;
    }
    $typeName = $type->getName();
    if ($typeName === "self" || $typeName === "static" || $typeName === "parent") {
      return false;
    }

    return is_a($request, $typeName);
  }

  public function executeController($callTarget, $callParams, &$controller)
  {
    try {
      $response = call_user_func_array($callTarget, array_values($callParams));
    } catch (GlobalException $e) {
      if ($e instanceof Error) {
        throw new Error($e->getMessage(), $e->statusCode, $e->errorCode, $e->errorDetails ?: $e->getTrace());
      } else {
        throw new Error($e->getMessage(), 500, "500:ServerError", $e->getTrace());
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

  /**
   * 启动应用：兜底实例化组件 → 生命周期启动 → 路由匹配 → 中间件执行 → 响应输出
   *
   * 处理流程：
   * 1. ensureInstances() 兜底实例化未注入的组件；
   * 2. fireBootUp() 触发启动钩子；
   * 4. 直接调用 App 持有的 Router 实例匹配当前请求（match()），命中参数与 append 隐式参数合并经 $request->params->fill() 注入，未命中抛 404 Error；
   * 5. 命中后构建控制器实例（构造期异常属代码/装配错误，交由全局异常处理器兜底）或闭包路由（预建默认 Controller 承载响应）；
   * 6. 通过 middleware->execute() 执行中间件链，内层依次执行 before()（参数校验失败在此短路）→ executeController() 业务 → after() 后处理；
   * 7. 中间件短路未进入内层时，兜底执行一次 after()
   * 8. fireShutdown() 触发结束钩子，输出响应并 exit。
   *
   * 异常路径：fireError() 触发错误钩子 → fireShutdown() 触发结束钩子 → 交由全局异常处理器输出。
   *
   * @return void 正常结束时输出并 exit，不会返回
   */
  public function run()
  {
    //* 延迟实例化兜底：setup() 未注入的组件在此自动实例化
    $this->ensureInstances();

    //* 载入扩展 ~ 输出：正常与异常结束都会执行结束钩子（异常交由全局异常处理器输出）
    try {
      //* 调用生命周期"启动"钩子
      $this->lifeCycle->fireBootUp();

      //* 启动已装载模块（模块随应用启动而启动，供后续路由/中间件使用）
      $this->bootModules();

      //* 路由：直接调用 App 持有的 Router 实例获取匹配结果，命中参数注入 $request->params
      $route = $this->router->route();

      if (!$route) {
        throw new Error("路由不存在", 404, 404, [
          "uri" => $this->request()->uri(),
          'method' => $this->request()->method()
        ]);
      }

      //* 路由参数 + append 隐式参数合并注入：append 为路由配置的不落在 URL 中的隐式传值
      //   （安全防护 / 上下文注入），同名时 append 覆盖 URL 路由参数，保证注入值权威
      $routeParams = array_merge($route['params'] ?: [], $route['append'] ?? []);
      if (!empty($routeParams)) {
        $this->request->params->fill($routeParams);
      }

      $callTarget = [];
      $callParams = $route['params'] ?: [];
      $routeInstantiateParams = $route['parameters'];
      $controller = null;
      if (isSafeCallable($route['controller'])) {
        $callTarget = $route['controller'];
        //* 闭包路由：逐参数按名绑定（保持声明顺序；$request 由框架注入，append 隐式参数可按名取值）
        $closure = new \ReflectionFunction($callTarget);
        $callParams = $this->bindClosureParams($closure->getParameters(), $routeParams, $routeInstantiateParams, $this->request);
        //* 预建默认 Controller 承载响应（中间件链 / after() / 输出均依赖它）
        $controller = new Controller($this->request);
      } else {
        //* 控制器缺失 / 类名错误时显式报 500，避免裸 Fatal（new null / 类不存在）
        $controllerClass = $route['controller'];
        if (!is_string($controllerClass) || !class_exists($controllerClass)) {
          throw new Error("路由控制器缺失或类不存在", 500, 500, [
            "uri" => $this->request()->uri(),
            "controller" => is_string($controllerClass) ? $controllerClass : gettype($controllerClass)
          ]);
        }
        $controller = new $controllerClass($this->request, ...$routeInstantiateParams);
        //* before() 已移入中间件内层执行：参数校验失败时同样需要经过中间件（CORS / 日志 / 耗时）
        $controllerHandleMethodName = is_null($route['methodName']) ? 'data' : $route['methodName'];
        if (!method_exists($controller, $controllerHandleMethodName)) {
          throw new Error("控制器缺少 $controllerHandleMethodName 方法");
        }
        $callTarget = [
          $controller,
          $controllerHandleMethodName
        ];
        //* 控制器方法：按名绑定参数
        $method = new \ReflectionMethod($controller, $controllerHandleMethodName);
        $callParams = $this->bindParamsByName($method->getParameters(), $route['params']);
      }

      //* 中间件链始终执行：控制器构造期异常属于代码/装配错误（由全局异常处理器兜底），
      //* 而 before() 的参数校验失败在内层短路，保证这类响应仍带上中间件设置的头部（如 CORS）
      $app = $this;
      $afterExecuted = false;
      $middlewareExecutedResult = $this->middleware->execute($route['middlewares'], $controller, function () use ($app, $callTarget, $callParams, &$controller, &$afterExecuted) {
        //* 洋葱内核：控制器生命周期 before → data → after
        $controller->before();
        if (!$controller->response->error) {
          $app->executeController($callTarget, $callParams, $controller);
          $controller->after();
        }
        $afterExecuted = true;

        return $controller->response;
      });
      //* 中间件链的最终返回即本次响应：无论成功/失败都以链的返回值为准
      //  （中间件短路时返回的是自建响应；正常穿透时返回的即内层的 $controller->response）
      if ($middlewareExecutedResult instanceof \kernel\Foundation\HTTP\Response) {
        $controller->response = $middlewareExecutedResult;
      }

      //* 兜底后处理：中间件短路（未进入内层，如鉴权直接返回）时补执行一次 after()
      if (!$afterExecuted) {
        $controller->after();
      }

      //* SSE 识别：长连接的推流必须在结束钩子/模块停止之前完成（推流期间仍会反复访问数据库 / Redis）
      $isServerSentEvent = $controller->response instanceof ServerSentEvent;

      $endTime = Date::milliseconds();

      // if ($this->request->preferredOutputType() === "json" && !$controller->response->OutputType()) {
      //   $controller->response->json();
      //   $controller->response->addBody([
      //     "requiredTime" => $endTime - $this->startTime . "ms"
      //   ]);
      // }

      //* 调用生命周期"结束"钩子
      $this->lifeCycle->fireShutdown($controller->response, [
        "exception" => null,
        "error" => false
      ]);

      //* 停止已装载模块（模块随应用结束而停止，释放资源）
      $this->shutdownModules();

      if (is_callable($controller->response->getData())) {
        call_user_func_array($controller->response->getData(), []);
      } else {
        $controller->response->output();
      }

      //* 响应已输出，正常结束：按约定 run() 不返回（终止执行，避免入口后续代码再次输出）
      exit(0);
    } catch (\Throwable $e) {
      //* 异常路径：先触发错误钩子（onError，统一上报/记录），再执行结束钩子（记录已执行到的步骤、释放资源），最后交由全局异常处理器输出
      $this->lifeCycle->fireError($e);
      $this->lifeCycle->fireShutdown(isset($controller) ? $controller->response : null, [
        "exception" => $e,
        "error" => true
      ]);
      //* 停止已装载模块（异常路径同样需释放资源）
      $this->shutdownModules();
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
  /**
   * 获取路由实例（未实例化则延迟实例化默认实例）
   *
   * 由 App 持有并实例化（加载路由表），匹配时经 getApp()->request() 获取当前请求。
   *
   * @return Router 路由实例
   */
  public function router()
  {
    if ($this->router === null) {
      $this->router = new Router();
    }
    return $this->router;
  }
  /**
   * 获取生命周期管理器（未实例化则延迟实例化默认实例）
   *
   * @return Lifecycle 生命周期管理器实例
   */
  public function lifeCycle()
  {
    if ($this->lifeCycle === null) {
      $this->lifeCycle = new Lifecycle();
    }
    return $this->lifeCycle;
  }
  /**
   * 获取中间件管理器（未实例化则延迟实例化默认实例）
   *
   * @return Middleware 中间件管理器实例
   */
  public function middleware()
  {
    if ($this->middleware === null) {
      $this->middleware = new Middleware();
    }
    return $this->middleware;
  }
  /**
   * 获取模块管理器（未实例化则延迟实例化默认实例）
   *
   * @return Modules 模块管理器实例
   */
  public function modules()
  {
    if ($this->modules === null) {
      $this->modules = new Modules();
    }
    return $this->modules;
  }
  /**
   * 启动所有已装载模块（供生命周期调用；模块未装载则无操作）
   *
   * @return void
   */
  protected function bootModules()
  {
    if ($this->modules !== null) {
      $this->modules->bootAll();
    }
  }
  /**
   * 停止所有已装载模块（供生命周期调用；模块未装载则无操作）
   *
   * @return void
   */
  protected function shutdownModules()
  {
    if ($this->modules !== null) {
      $this->modules->shutdownAll();
    }
  }
  /**
   * 获取默认的 App 实例
   *
   * 实例化 App（new App($id)）时自动登记到 {@see Applications}：
   * **首个注册（或显式指定）的应用为默认应用**，多个实例共存时可用
   * {@see Applications::setDefault()} 切换。
   * 全局函数 getApp() 等价于本方法。
   *
   * @return App|null 尚未登记任何 App 时返回 null
   */
  public static function getInstance()
  {
    return Applications::default();
  }
  /**
   * 获取默认应用的ID（id），即项目文件夹名称
   *
   * 取默认应用实例的第一个构造参数。
   *
   * @return string|null 尚未登记任何 App 时返回 null
   */
  public static function id()
  {
    $app = Applications::default();

    return $app === null ? null : $app->id;
  }
  /**
   * 获取内核的ID（KernelId），即内核目录文件夹名称，默认 "kernel"
   *
   * 取默认应用实例的第二个构造参数。
   *
   * @return string|null 尚未登记任何 App 时返回 null
   */
  public static function kernelId()
  {
    $app = Applications::default();

    return $app === null ? null : $app->kernelId;
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
