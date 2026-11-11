<?php

namespace kernel\Foundation;

use kernel\Facades\Log;
use Exception as GlobalException;
use kernel\Foundation\Application\Application;
use kernel\Foundation\Application\Applications;
use kernel\Providers\App\ConfigProvider;
use kernel\Providers\App\LogProvider;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\Date;
use kernel\Foundation\Data\Func;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response\ServerSentEvent;
use kernel\Providers\App\LifecycleProvider;
use kernel\Foundation\Middleware\Middleware;
use kernel\Foundation\Router\Router;
use kernel\Providers\App\CacheProvider;

/**
 * Web 应用：框架零件登记 + 装配 + 请求执行流程
 *
 * 身份（ID / kernelId）、容器、组件登记与取用全在基类 {@see Application} 里，
 * 本类只负责 Web 这一层：装配（{@see assemble()}）、执行（{@see run()}）、
 * 控制器调用（{@see executeController()}）与路由参数绑定。
 *
 * 组件一律由容器持有，App 不为组件保留属性；`$app->xxx()` 由基类
 * {@see Application::__call()} 按类短名解析到容器。新增组件只需在装配期绑定，
 * 不需要改这个类。
 *
 * @method Request    request()    当前请求（请求级，跨应用共享同一份）
 * @method Router     router()     路由表与路由匹配（请求级）
 * @method LifecycleProvider  lifeCycle()  生命周期钩子（请求级）
 * @method Middleware middleware() 中间件链（应用级）
 * @method ConfigProvider     config()     本应用配置（应用级）
 *
 * @see Application 身份、容器与组件装配
 * @see Application::__call() 短名到容器的解析规则
 */
class App extends Application
{
  /**
   * 构造：基座 + 框架零件 + 装配
   *
   * 三步，顺序不能换：
   * 1. 基座（身份、容器、启动时间、登记到应用管理器）由
   *    {@see Application::__construct()} 准备；
   * 2. 登记框架零件：App 自身、ConfigProvider / LogProvider / Middleware，以及请求级三件套
   *    Request / Router / LifecycleProvider（只登记、不构造）；
   * 3. 执行装配（{@see assemble()}）——Bootstrap 里第一句就要 `register()`，因此必须排在最后。
   *
   * 构造完成即「配置好的、可 run() 的应用」，不再有「new 完还得记得 setup」的半成品状态。
   *
   * 构造期零副作用是懒装配的前提：{@see Application::of()} 懒装配别的应用时传 `null`，
   * 不会顺带把对方的请求、路由表、钩子链拉起来。
   *
   * 路由表由 Router 加载，匹配所需的当前请求经 `$app->request()` 获取。CLI 下 Request 的
   * URI 由 Console::handle() 置为命中的命令名；DiscuzX 下由 `DiscuzXApp::run()` 在
   * 处理请求前写入。
   *
   * @param string|callable|null $setup 装配类名或装配闭包；null 表示只建骨架、不装配
   * @param string $id 应用唯一标识，跟项目目录同名
   * @param string $kernelId 内核 id，默认 kernel
   */
  public function __construct($setup = null, $id = null, $kernelId = "kernel")
  {
    parent::__construct($id, $kernelId);

    //* 框架零件：只登记、不构造——首次取用才反射实例化
    //* 名字即键：容器只做精确匹配，短名必须在这里显式给别名
    $this->provider($this);
    $this->defer(ConfigProvider::class, "config");
    $this->defer(LogProvider::class, "log");
    $this->defer(Middleware::class, "middleware");
    $this->defer(Request::class, "request");
    $this->defer(Router::class, "router");
    $this->defer(LifecycleProvider::class, "lifecycle");

    //* 请求态状态：容器不再区分请求级，它们就是普通单例（每进程一份）
    //* 认证态已并入 AuthProvider，它构造时自登记，这里不用再 defer
    $this->defer(CacheProvider::class, "cache");

    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\ExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Foundation\Exception\ExceptionHandler::handle", E_ALL);

    $this->assemble($setup);
  }

  /**
   * 应用装配：执行装配类或装配闭包（构造末尾调用，不再对外暴露）
   *
   * 装配期只做「登记」——`register()` / `bind()` / `import()`，不在这里 new 组件；
   * 要读设置、建磁盘池、挂中间件，写进对应 Provider 的构造函数
   * （`$app->register(new XxxProvider())`，构造即装配）。
   *
   * - 传类名：执行该类构造（Bootstrap 无参构造，应用实例由基类 `app()` 从装配上下文取）；
   * - 传闭包：执行，参数为当前 App 实例——生命周期钩子可以在这里注册
   *   （{@see onBootUp()} / {@see onShutdown()} / {@see onError()}）；
   * - 传 null：只建骨架、不装配，用于 {@see Application::of()} 的懒装配路径。
   *
   * 之所以留成独立方法而不是把逻辑内联进构造函数：{@see \kernel\DiscuzX\Foundation\DiscuzXApp}
   * 要先把请求 URI 写好再装配（Bootstrap 里可能读 URI），需要一个「构造执行到中途」
   * 的落点。
   *
   * @param string|callable|null $call 装配类名、装配闭包，或 null
   * @return void
   */
  protected function assemble($call)
  {
    if ($call === null) {
      return;
    }

    if (is_string($call)) {
      if (!class_exists($call)) {
        return;
      }

      //* 构造期间压入装配上下文：Bootstrap 里 $this->app() 拿到的是本应用，
      //* 而不是默认应用（子系统回落到默认应用会把 import 绑定写进别的应用的容器）。
      Applications::enterSetup($this);
      try {
        new $call();
        //* 装完才置位：跨应用 import 靠它判断提供方是否真的装配过
        $this->assembled = true;
      } finally {
        Applications::leaveSetup();
      }
      return;
    }

    if (is_callable($call)) {
      $call($this);
      $this->assembled = true;
    }
  }

  // ==========================================================================
  //  生命周期（委托 LifecycleProvider，请求级）
  // ==========================================================================

  /**
   * 注册启动钩子或启动装配类
   *
   * 委托给请求级的 {@see LifecycleProvider}：状态（是否已触发、捕获的异常）跟着请求走，
   * 常驻进程下由容器 `flush()` 一并清掉，不会串到下一轮。
   *
   * 装配期（构造参数里的闭包 / Bootstrap）即可注册：
   * ```php
   * new DiscuzXApp(function ($app) {
   *   $app->onBootUp(Bootup::class);
   *   $app->onShutdown(Shutdown::class);
   * });
   * ```
   *
   * @param string|callable|null $callback 装配类名、启动回调；null 时用 {App}\Setup\Bootup
   * @return $this
   */
  public function onBootUp($callback = null)
  {
    $this->lifeCycle()->onBootUp($callback);

    return $this;
  }

  /**
   * 注册结束钩子或结束装配类
   *
   * 正常结束与异常结束都会执行。回调签名 `function ($response, $context)`。
   *
   * @param string|callable $callback 装配类名或结束回调
   * @return $this
   */
  public function onShutdown($callback)
  {
    $this->lifeCycle()->onShutdown($callback);

    return $this;
  }

  /**
   * 注册错误钩子
   *
   * 请求处理过程中捕获到异常时触发，回调签名 `function (\Throwable $e)`。
   *
   * @param callable $callback 错误回调
   * @return $this
   */
  public function onError($callback)
  {
    $this->lifeCycle()->onError($callback);

    return $this;
  }

  /**
   * 启动应用：提交组件 → 生命周期启动 → 路由匹配 → 中间件执行 → 响应输出
   *
   * 主流程四步，细节在下列私有方法里：
   * 1. 开发模式下把依赖图写进日志（此时装配已结束）；
   * 2. {@see LifecycleProvider::fireBootUp()} 触发启动钩子，随后 {@see matchRoute()} 匹配路由并注入参数
   *    （未命中抛 404）、{@see resolveController()} 构建控制器 / 闭包与实参
   *    （构造期异常属代码/装配错误，交由全局异常处理器兜底）；
   * 3. {@see runMiddleware()} 执行中间件链，内层依次 before() → {@see executeController()} → after()；
   *    中间件短路未进入内层时兜底补一次 after()；
   * 4. {@see outputResponse()} 触发结束钩子、输出响应并 exit。
   *
   * 异常路径：fireError() 触发错误钩子 → fireShutdown() 触发结束钩子 → 交由全局异常处理器输出。
   *
   * @return void 正常结束时输出并 exit，不会返回
   */
  public function run()
  {
    //* 载入扩展 ~ 输出：正常与异常结束都会执行结束钩子（异常交由全局异常处理器输出）
    try {
      //* 开发模式：把依赖图写进日志（谁 import 了谁、谁开放了什么），生产不写。
      //* 这里显式判定模式：请求期配置是必须加载的（宿主环境已就绪），顺带把 mode 定下来
      if ($this->isDevelopment()) {
        Log::debug("app.dependencyGraph", Applications::graph());
      }

      //* 调用生命周期"启动"钩子
      $this->lifeCycle()->fireBootUp();

      //* 路由匹配 → 参数注入 → 调用目标（控制器 / 闭包）与实参
      list($route, $routeParams) = $this->matchRoute();
      list($controller, $callTarget, $callParams) = $this->resolveController($route, $routeParams);

      //* 中间件链 → 结束钩子 → 输出（正常结束不返回）
      $this->runMiddleware($route, $controller, $callTarget, $callParams);
      $this->outputResponse($controller);
    } catch (\Throwable $e) {
      //* 异常路径：先触发错误钩子（onError，统一上报/记录），再执行结束钩子（记录已执行到的步骤、释放资源），最后交由全局异常处理器输出
      $this->lifeCycle()->fireError($e);
      $this->lifeCycle()->fireShutdown(isset($controller) ? $controller->response : null, [
        "exception" => $e,
        "error" => true
      ]);
      throw $e;
    }
  }

  /**
   * 匹配当前请求的路由，并把路由参数注入请求
   *
   * 未命中直接抛 404 Error；append 隐式参数（安全防护 / 上下文注入）与 URL 路由参数合并，
   * 同名时 append 覆盖 URL 值，保证注入值权威。
   *
   * @return array{0: array, 1: array} [路由匹配结果, 合并后的路由参数]
   * @throws Error 未命中路由时抛出 404
   */
  private function matchRoute()
  {
    //* 直接调用 App 持有的 Router 实例获取匹配结果
    $route = $this->router()->route();

    if (!$route) {
      throw new Error("路由不存在", 404, 404, [
        "uri" => $this->request()->uri(),
        'method' => $this->request()->method()
      ]);
    }

    $routeParams = array_merge($route['params'] ?: [], $route['append'] ?? []);
    if (!empty($routeParams)) {
      $this->request()->params->fill($routeParams);
    }

    return [$route, $routeParams];
  }

  /**
   * 构建调用目标：控制器实例 + [调用目标, 实参]
   *
   * 两条分支：
   * - 闭包路由：预建默认 Controller 承载响应，参数按名绑定（含 `$request` 注入与 append 隐式参数）；
   * - 控制器路由：反射实例化（构造参数按 `->parameters()` 展开），参数按名绑定。
   *
   * @param array $route 路由匹配结果
   * @param array $routeParams 合并后的路由参数
   * @return array{0: Controller, 1: callable|array, 2: array} [控制器, 调用目标, 实参]
   * @throws Error 控制器缺失 / 类不存在 / 方法不存在时抛出 500
   */
  private function resolveController($route, $routeParams)
  {
    $callTarget = [];
    $callParams = $route['params'] ?: [];
    $routeInstantiateParams = $route['parameters'];
    $controller = null;

    if (Func::isSafeCallable($route['controller'])) {
      $callTarget = $route['controller'];
      //* 闭包路由：逐参数按名绑定（保持声明顺序；$request 由框架注入，append 隐式参数可按名取值）
      $closure = new \ReflectionFunction($callTarget);
      $callParams = $this->bindClosureParams($closure->getParameters(), $routeParams, $routeInstantiateParams, $this->request());
      //* 预建默认 Controller 承载响应（中间件链 / after() / 输出均依赖它）
      $controller = new Controller($this->request());
    } else {
      //* 控制器缺失 / 类名错误时显式报 500，避免裸 Fatal（new null / 类不存在）
      $controllerClass = $route['controller'];
      if (!is_string($controllerClass) || !class_exists($controllerClass)) {
        throw new Error("路由控制器缺失或类不存在", 500, 500, [
          "uri" => $this->request()->uri(),
          "controller" => is_string($controllerClass) ? $controllerClass : gettype($controllerClass)
        ]);
      }
      $controller = new $controllerClass($this->request(), ...$routeInstantiateParams);
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

    return [$controller, $callTarget, $callParams];
  }

  /**
   * 执行中间件链，并把链的返回值回填到控制器响应
   *
   * 中间件始终执行：控制器构造期异常属于代码/装配错误（由全局异常处理器兜底），
   * 而 before() 的参数校验失败在内层短路，保证这类响应仍带上中间件设置的头部（如 CORS）。
   * 中间件短路未进入内层时，兜底执行一次 after()。
   *
   * @param array $route 路由匹配结果（取 middlewares）
   * @param Controller $controller 控制器实例（按引用：内层可能替换响应对象）
   * @param callable|array $callTarget 调用目标
   * @param array $callParams 实参
   * @return void
   */
  private function runMiddleware($route, &$controller, $callTarget, $callParams)
  {
    $app = $this;
    $afterExecuted = false;
    $middlewareExecutedResult = $this->middleware()->execute($route['middlewares'], $controller, function () use ($app, $callTarget, $callParams, &$controller, &$afterExecuted) {
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
  }

  /**
   * 结束钩子 + 响应输出（正常路径的终点，不返回）
   *
   * SSE 推流必须在结束钩子 / 模块停止之前完成——推流期间仍会反复访问数据库 / Redis。
   *
   * @param Controller $controller 控制器实例
   * @return void 输出后 exit，不会返回
   */
  private function outputResponse($controller)
  {
    //* 调用生命周期"结束"钩子
    $this->lifeCycle()->fireShutdown($controller->response, [
      "exception" => null,
      "error" => false
    ]);

    if (is_callable($controller->response->getData())) {
      call_user_func_array($controller->response->getData(), []);
    } else {
      $controller->response->output();
    }

    //* 响应已输出，正常结束：按约定 run() 不返回（终止执行，避免入口后续代码再次输出）
    exit(0);
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
      $controller = new Controller($this->request());
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

  // ==========================================================================
  //  路由参数绑定
  // ==========================================================================

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
}
