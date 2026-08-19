<?php

namespace kernel\Foundation;

/**
 * 生命周期管理器
 *
 * 统一管理应用的生命周期钩子：引导（bootUp）、结束（shutdown）、错误（error）。
 * - onBootUp() / onShutdown() 双语义：传类名注册"装配类"（触发时实例化、构造即装配），
 *   传回调注册"钩子"（触发时直接调用）。
 * - fireBootUp() / fireShutdown() / fireError() 由 App::run() / Console::handle() 在对应时机触发，
 *   触发后各自置幂等标记，保证同一请求只执行一次。
 * - 启动已触发后（bootupFired）再注册的启动回调会立即执行，避免注册后永不执行。
 * - fireError() 会记录当前异常，可通过 exception() 读取（App::exception() 委托本方法）。
 *
 * 应用侧只需使用 App / Controller 上的便捷方法（$app->onBootUp()、$this->onShutdown() 等）。
 */
class Lifecycle
{
  /**
   * 当前应用 ID（用于 onBootUp() 不传参时构造默认装配类名 {App}\Lifecycle\Bootup）
   *
   * @var string|null
   */
  protected $appId = null;
  /**
   * 当前请求实例（启动钩子回调与装配类的构造参数；CLI 下 URI 为命中的命令名）
   *
   * @var \kernel\Foundation\HTTP\Request|null
   */
  protected $request = null;
  /**
   * 引导钩子/装配类注册表
   *
   * @var array
   */
  protected $bootUp = [];
  /**
   * 结束钩子/装配类注册表
   *
   * @var array
   */
  protected $shutdown = [];
  /**
   * 错误钩子注册表
   *
   * @var array
   */
  protected $error = [];
  /**
   * 是否已注册过引导装配类（幂等，避免重复实例化）
   *
   * @var bool
   */
  protected $bootupLoaded = false;
  /**
   * 是否已注册过关闭装配类（幂等，避免重复实例化）
   *
   * @var bool
   */
  protected $shutdownLoaded = false;
  /**
   * 启动钩子是否已触发（触发后注册的启动回调立即执行）
   *
   * @var bool
   */
  protected $bootupFired = false;
  /**
   * 结束钩子是否已触发（保证异常路径只执行一次）
   *
   * @var bool
   */
  protected $shutdownFired = false;
  /**
   * 请求处理过程中捕获的异常对象，结束/错误钩子可读取
   *
   * @var \Throwable|null
   */
  protected $exception = null;

  /**
   * 构建生命周期管理器
   *
   * @param string|null $appId 应用 ID（用于 onBootUp() 不传参时的默认装配类名 {App}\Lifecycle\Bootup）
   * @param \kernel\Foundation\HTTP\Request|null $request 当前请求实例（HTTP 与 CLI 均实例化）
   */
  public function __construct($appId = null, $request = null)
  {
    $this->appId = $appId;
    $this->request = $request;
  }

  /**
   * 注册应用引导装配类（Lifecycle\Bootup），或注册启动钩子
   *
   * 双语义方法：
   * - 传字符串（类名）或不传：注册启动装配类——由 run() 在请求到达后、路由匹配前
   *   实例化（构造即装配，构造参数为当前请求 $request；CLI 下同样收到 Request 实例，
   *   其 URI 为命中的命令名），可立即执行注册事件、按需引入文件、初始化数据库连接等；
   *   只注册一次（幂等），类不存在时静默跳过。
   * - 传回调（闭包/数组等）：注册启动钩子。
   *
   * 不传类名时默认使用 {App}\Lifecycle\Bootup（应用命名空间下的 Lifecycle 目录引导类）。
   *
   * @param string|\Closure|array|null $callback 应用引导装配类名，或启动钩子回调；不传时默认 {App}\Lifecycle\Bootup
   * @return $this
   */
  public function onBootUp($callback = null)
  {
    if ($callback === null) {
      $callback = $this->appId . "\\Lifecycle\\Bootup";
    }

    if (is_string($callback)) {
      if ($this->bootupLoaded) {
        return $this;
      }
      if (class_exists($callback)) {
        $this->bootupLoaded = true;
        //* 注册启动装配：run() 中请求到达后、路由匹配前实例化（构造即装配，收到 $request）
        array_push($this->bootUp, $callback);
      }

      return $this;
    }

    //* 启动阶段已触发后（run() 已进入业务阶段）注册的启动回调：立即执行，避免注册后永不执行
    if ($this->bootupFired) {
      $callback($this->request);
      return $this;
    }

    array_push($this->bootUp, $callback);

    return $this;
  }

  /**
   * 注册应用关闭装配类（Lifecycle\Shutdown），或注册关闭钩子
   *
   * 双语义方法：
   * - 传字符串（类名）：注册关闭装配类——由 run() 在请求结束（正常或异常）时实例化
   *   （构造即装配，构造参数为 $controller->response；CLI 下传命令退出码），可立即执行
   *   记录日志、释放资源等；只注册一次（幂等），类不存在时静默跳过。
   * - 传回调（闭包/数组等）：注册关闭钩子。回调签名 function ($response, $context = null)：
   *   - $response：当前响应对象（异常路径可能为 null；CLI 下为命令退出码）
   *   - $context["exception"]：非空表示异常结束（可通过 exception() 读取）
   *   - $context["error"]：是否异常结束
   *   关闭钩子无论请求正常结束还是异常结束都会执行（异常必达）。
   *
   * @param callable|string $callback 回调函数，或关闭装配类名（如 \myapp\Lifecycle\Shutdown::class）
   * @return $this
   */
  public function onShutdown($callback)
  {
    if (is_string($callback)) {
      if ($this->shutdownLoaded) {
        return $this;
      }
      if (class_exists($callback)) {
        $this->shutdownLoaded = true;
        //* 注册关闭装配：run() 在请求结束（正常或异常）时实例化（构造即装配，收到 $controller->response）
        array_push($this->shutdown, $callback);
      }

      return $this;
    }

    array_push($this->shutdown, $callback);

    return $this;
  }

  /**
   * 注册错误钩子（请求处理过程中捕获到异常时触发）
   *
   * 异常路径下先触发错误钩子（onError），再触发结束钩子（onShutdown），
   * 最后交由全局异常处理器输出。可叠加、可重复注册，按注册顺序依次执行；同一异常只触发一次。
   *
   * @param callable $callback 错误钩子回调，function (\Throwable $exception)
   * @return $this
   */
  public function onError($callback)
  {
    array_push($this->error, $callback);

    return $this;
  }

  /**
   * 触发生命周期"启动"钩子
   *
   * 遍历 bootUp 注册表：可调用对象直接执行（收到 $request），类名则实例化（构造即装配）。
   * 触发后将 bootupFired 置位，此后 onBootUp(闭包) 注册的启动回调将立即执行。
   *
   * @return void
   */
  public function fireBootUp()
  {
    if ($this->bootUp) {
      $this->bootupFired = true;
      foreach ($this->bootUp as $item) {
        if (is_callable($item)) {
          $item($this->request);
        } else {
          new $item($this->request);
        }
      }
    }
  }

  /**
   * 触发生命周期"结束"钩子
   *
   * 正常结束与异常结束都会执行（run() 的 catch 分支也会调用），保证有始有终。
   * 遍历 shutdown 注册表：可调用对象执行时传入 ($arg, $context)，类名则以 $arg 单参数
   * 实例化（构造即装配，兼容只接收 $response/$exitCode 的装配类）。
   * 同一请求只触发一次（shutdownFired 幂等），避免异常路径重复执行。
   *
   * @param mixed $arg 传给钩子的数据（HTTP：$controller->response；CLI：命令退出码；异常路径可能为 null）
   * @param array|null $context 结束上下文：["exception" => Throwable|null, "error" => bool, "preflight" => bool]
   * @return void
   */
  public function fireShutdown($arg = null, $context = null)
  {
    if ($this->shutdownFired) {
      return;
    }
    if ($this->shutdown) {
      $this->shutdownFired = true;
      foreach ($this->shutdown as $item) {
        if (is_callable($item)) {
          $item($arg, $context);
        } else {
          new $item($arg);
        }
      }
    }
  }

  /**
   * 触发"错误"钩子（请求处理过程中捕获异常时执行）
   *
   * 由 App::run() / Console::handle() 的 catch 分支调用：按注册顺序执行 onError() 注册的
   * 错误钩子（每个回调收到异常对象 $exception），之后才执行结束钩子并抛给全局异常处理器。
   * 同时记录当前异常，供 exception() 读取。
   *
   * @param \Throwable $exception 当前请求捕获的异常对象
   * @return void
   */
  public function fireError($exception)
  {
    $this->exception = $exception;
    foreach ($this->error as $item) {
      if (is_callable($item)) {
        $item($exception);
      }
    }
  }

  /**
   * 获取当前请求处理过程中捕获的异常
   *
   * 正常结束返回 null；控制器/中间件/引导等任意环节抛出并被框架捕获后，
   * shutdown 回调可通过该方法判断是否异常结束、读取错误信息。
   *
   * @return \Throwable|null
   */
  public function exception()
  {
    return $this->exception;
  }
}
