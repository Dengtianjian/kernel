<?php

namespace kernel\Foundation\Application;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Object\BaseObject;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * 应用容器
 *
 * 四张表，按「什么时候构造」区分，键统一是「别名，没有别名就用类名」：
 * - `$providers`：名字 => 实例。**登记即构造**，取用直接返回，适合装配类（构造即装配）；
 * - `$deferred` ：名字 => 实现体。登记不构造，首次取用才建，适合框架零件这类构造有副作用的；
 * - `$bindings` ：名字 => 实现体。每次取用都新建，取用时传的参数即构造参数；
 * - `$imports`  ：名字 => 来源。只记 appId 与转发目标，实际构造发生在提供方容器。
 *
 * 实现体支持四种形态，三张「按需构造」的表共用一套解析：
 * 类名（反射构造，参数自动注入）/ 实例 / 闭包（调用并注入）/ `[类名, 方法]`（工厂，每次新建）。
 *
 * 一个名字只能出现在一张表里：同名重复登记直接报错，不做「后登记覆盖」。
 * 登记时给了别名，就**只**登记别名；但取用时若写的是实现类的完整类名，
 * 会按实现体反查一次（{@see findByConcrete()}），保证 `make(类名)` 与 `$app->别名()`
 * 指向同一个条目。
 *
 * @see Application::__call() `$app->xxx()` 的实现
 */
class Container
{
  /**
   * 所属应用
   *
   * @var Application
   */
  protected $app = null;
  /**
   * 已构造的单例：名字 => 实例
   *
   * @var array<string, object>
   */
  protected $providers = [];
  /**
   * 延迟构造的单例：名字 => 实现体
   *
   * @var array<string, mixed>
   */
  protected $deferred = [];
  /**
   * 多例：名字 => 实现体，每次取用新建
   *
   * @var array<string, mixed>
   */
  protected $bindings = [];
  /**
   * 跨应用来源：名字 => ['appId' => 提供方, 'target' => 提供方容器里的名字]
   *
   * @var array<string, array{appId:string,target:string}>
   */
  protected $imports = [];
  /**
   * 正在构造的实现体（循环依赖检测）
   *
   * @var array<string, bool>
   */
  protected $resolving = [];

  public function __construct(Application $app)
  {
    $this->app = $app;
  }

  // ==========================================================================
  //  登记
  // ==========================================================================

  /**
   * 登记单例：**登记即构造**
   *
   * 传类名/闭包/工厂时当场构造一次并入表，之后取用返回同一实例——适合「构造即装配」
   * 的类（挂中间件、建磁盘池、登记账号）。构造会带来的副作用因此发生在装配期。
   *
   * 传实例时直接入表（归属只在为空时补，见 {@see bindApp()}）。
   *
   * 需要「登记但不构造」请用 {@see defer()}。
   *
   * @param object|string|callable|array $concrete 实现体：类名 / 实例 / 闭包 / [类名, 方法]
   * @param string|null $name 取用名；省略时用实现体的类名
   * @return $this
   * @throws Error 名字冲突、实现体无法识别或构造失败时抛出
   */
  public function provider($concrete, $name = null)
  {
    $name = $this->resolveName($concrete, $name);
    $key = $this->normalize($name);
    $this->assertNameFree($key, $name);

    if (is_object($concrete) && !($concrete instanceof \Closure)) {
      $this->providers[$key] = $this->bindApp($concrete);

      return $this;
    }

    $this->providers[$key] = $this->bindApp($this->buildConcrete($concrete, [], $name));

    return $this;
  }

  /**
   * 登记延迟单例：登记不构造，首次取用才建
   *
   * 用于「构造有副作用、但希望等真正用到再付代价」的类（框架零件：Request / Router
   * / Lifecycle / Middleware）；传实例时与 {@see provider()} 等价。
   *
   * @param object|string|callable|array $concrete 实现体
   * @param string|null $name 取用名；省略时用实现体的类名
   * @return $this
   * @throws Error 名字冲突时抛出
   */
  public function defer($concrete, $name = null)
  {
    $name = $this->resolveName($concrete, $name);
    $key = $this->normalize($name);
    $this->assertNameFree($key, $name);

    if (is_object($concrete) && !($concrete instanceof \Closure)) {
      $this->providers[$key] = $this->bindApp($concrete);

      return $this;
    }

    $this->deferred[$key] = $concrete;

    return $this;
  }

  /**
   * 登记多例：每次取用新建
   *
   * 取用时传入的参数即构造参数（`$app->foo($a, $b)`）。传实例时退化为单例
   * ——「每次新建」对现成对象没有意义。
   *
   * @param object|string|callable|array $concrete 实现体
   * @param string|null $name 取用名；省略时用实现体的类名
   * @return $this
   * @throws Error 名字冲突时抛出
   */
  public function bind($concrete, $name = null)
  {
    $name = $this->resolveName($concrete, $name);
    $key = $this->normalize($name);
    $this->assertNameFree($key, $name);

    if (is_object($concrete) && !($concrete instanceof \Closure)) {
      $this->providers[$key] = $this->bindApp($concrete);

      return $this;
    }

    $this->bindings[$key] = $concrete;

    return $this;
  }

  /**
   * 声明「某个名字由另一个应用提供」
   *
   * 只记来源，不构造；取用时转发到提供方容器，由提供方那条登记的语义决定构造时机
   * （实例直接返回、延迟的首次构造、多例的每次新建）。
   *
   * 三种形态：
   * - `import(类名, appId, 别名)`：最常用；
   * - `import(类名)`：按根命名空间推导 appId（`kernel\` 下的类无归属，必须给 appId）；
   * - `import($实例, 名字)`：等价 {@see provider()}——实例自带归属，不需要声明来源。
   *
   * 转发要求提供方**已装配**（{@see Application::assembled()}），否则会明确报「尚未装配」。
   *
   * @param string|object $source 类名或实例
   * @param string|null $appId 提供方应用 ID；来源是实例时该位置是取用名
   * @param string|null $alias 取用别名
   * @return $this
   * @throws Error 推导不出 appId 或名字冲突时抛出
   */
  public function import($source, $appId = null, $alias = null)
  {
    if (is_object($source)) {
      return $this->provider($source, $appId ?? $alias);
    }

    $class = ltrim((string) $source, "\\");

    if ($appId === null) {
      $appId = Application::owner($class);
      if ($appId === null) {
        throw new Error(
          "无法从 {$class} 推导所属应用（kernel 下的类无归属），请显式传入 appId",
          500,
          "500:UnknownApp"
        );
      }
    }

    $name = $alias ?? $class;
    $key = $this->normalize($name);
    $this->assertNameFree($key, $name);

    $this->imports[$key] = ["appId" => $appId, "target" => $class];

    return $this;
  }

  // ==========================================================================
  //  取用
  // ==========================================================================

  /**
   * 取用（唯一入口）
   *
   * 名字先按「别名 / 完整类名」精确命中；没命中且是完整类名时，按实现体反查
   * （{@see findByConcrete()}），再退到反射构造。`$app->xxx(...)` 是它的语法糖。
   *
   * @param string $name 登记名或完整类名
   * @param array $params 构造参数（仅多例生效；位置或命名均可）
   * @return object
   * @throws Error 名字不存在且不是可用类名、单例传参、提供方未装配时抛出
   */
  public function make($name, array $params = [])
  {
    $key = $this->normalize($name);

    if (isset($this->providers[$key])) {
      if (!empty($params)) {
        throw new Error("{$name} 是单例，不接受构造参数；需要每次新建请用 bind() 登记", 500, "500:SingletonArguments");
      }

      return $this->providers[$key];
    }

    if (isset($this->deferred[$key])) {
      $concrete = $this->deferred[$key];
      unset($this->deferred[$key]);
      $this->providers[$key] = $this->bindApp($this->buildConcrete($concrete, $params, $name));

      return $this->providers[$key];
    }

    if (isset($this->bindings[$key])) {
      return $this->bindApp($this->buildConcrete($this->bindings[$key], $params, $name));
    }

    if (isset($this->imports[$key])) {
      return $this->resolveImported($name, $this->imports[$key], $params);
    }

    //* 名字没命中：可能是「登记时给了别名、这里写的是实现类名」，按实现体反查一次
    $actual = $this->findByConcrete($name);
    if ($actual !== null) {
      return $this->make($actual, $params);
    }

    $class = ltrim($name, "\\");
    if (class_exists($class)) {
      //* 兜底构造也要绑归属：否则对象 app() 会落到默认应用，表名/路径跟着错
      return $this->bindApp($this->buildClass($class, $params));
    }

    throw new Error(
      "无法解析 {$name}：既没有登记，也不是可用的类名",
      500,
      "500:UnknownComponent"
    );
  }

  /**
   * 该名字是否已登记
   *
   * 登记名命中，或「按实现体反查」命中，都算有。不构造。
   *
   * @param string $name 登记名或完整类名
   * @return bool
   */
  public function has($name)
  {
    $key = $this->normalize($name);

    if (isset($this->providers[$key]) || isset($this->deferred[$key]) || isset($this->bindings[$key]) || isset($this->imports[$key])) {
      return true;
    }

    return $this->findByConcrete($name) !== null;
  }

  /**
   * 已构造的单例（排查用）
   *
   * @return array<string, object> 名字 => 实例
   */
  public function providers()
  {
    return $this->providers;
  }

  /**
   * 已登记的跨应用来源
   *
   * @return array<string, string> 名字 => 提供方 appId
   */
  public function imports()
  {
    $imports = [];
    foreach ($this->imports as $name => $entry) {
      $imports[$name] = $entry["appId"];
    }

    return $imports;
  }

  // ==========================================================================
  //  跨应用
  // ==========================================================================

  /**
   * 把取用转发到提供方容器
   *
   * @param string $name 本应用里的名字（错误信息用）
   * @param array $entry 来源登记
   * @param array $params 构造参数
   * @return object
   * @throws Error 提供方未装配时抛出
   */
  protected function resolveImported($name, $entry, array $params = [])
  {
    $owner = Applications::get($entry["appId"]);

    if ($owner === null || !$owner->assembled()) {
      throw new Error(
        "应用 {$entry["appId"]} 尚未装配，无法导入 {$name}（要求该应用先完整构造一次，而不是 Application::of() 建出的骨架）",
        500,
        "500:AppNotAssembled"
      );
    }

    return $owner->container()->make($entry["target"], $params);
  }

  /**
   * 按实现体反查登记名
   *
   * 登记时给别名就只记别名，调用点却常写完整类名（`make(Storage::class)`、
   * 门面的 `has(类名)`）。这里扫一遍找出「实现体就是这个类」的条目，保证两条路
   * 指向同一份，而不是反射新建一个孤儿。
   *
   * @param string $name 完整类名
   * @return string|null 命中的登记名；没命中或命中多个（歧义）时返回 null
   */
  protected function findByConcrete($name)
  {
    $class = ltrim((string) $name, "\\");
    //* 只对「确实是类名」的输入反查；别名（如 config）不参与
    if ($class === "" || !class_exists($class)) {
      return null;
    }

    $found = [];
    foreach ($this->deferred as $key => $concrete) {
      if (is_string($concrete) && ltrim($concrete, "\\") === $class) {
        $found[] = $key;
      }
    }
    foreach ($this->bindings as $key => $concrete) {
      if (is_string($concrete) && ltrim($concrete, "\\") === $class) {
        $found[] = $key;
      }
    }
    foreach ($this->imports as $key => $entry) {
      if ($entry["target"] === $class) {
        $found[] = $key;
      }
    }
    foreach ($this->providers as $key => $instance) {
      if (get_class($instance) === $class) {
        $found[] = $key;
      }
    }

    if (count($found) > 1) {
      throw new Error(
        "{$class} 对应多个登记名：" . implode(" / ", $found) . "，请改用别名取用",
        500,
        "500:AmbiguousEntry"
      );
    }

    return $found[0] ?? null;
  }

  // ==========================================================================
  //  构造与注入
  // ==========================================================================

  /**
   * 按实现体构造实例
   *
   * @param mixed $concrete 类名 / 闭包 / [类名, 方法]
   * @param array $args 调用方传入的构造参数
   * @param string $expect 期望的类名（用于校验工厂返回值）
   * @return object
   * @throws Error 返回值类型不符时抛出
   */
  protected function buildConcrete($concrete, array $args = [], $expect = "")
  {
    if ($concrete instanceof \Closure) {
      //* 闭包里可能自己 new 对象，同样要有归属上下文
      Applications::enterSetup($this->app);
      try {
        $result = $concrete(...$this->resolveParameters(new ReflectionFunction($concrete), $args));
      } finally {
        Applications::leaveSetup();
      }

      return $this->assertConcrete($result, $expect);
    }

    if (is_array($concrete) && count($concrete) === 2) {
      //* 工厂每次新建：不缓存工厂类，避免工厂自身状态串味
      list($factoryClass, $method) = $concrete;
      $factory = $this->bindApp($this->buildClass($factoryClass, []));

      Applications::enterSetup($this->app);
      try {
        $result = $factory->$method(...$this->resolveParameters(new ReflectionMethod($factory, $method), $args));
      } finally {
        Applications::leaveSetup();
      }

      return $this->assertConcrete($result, $expect);
    }

    if (is_object($concrete)) {
      return $concrete;
    }

    if (is_string($concrete)) {
      return $this->buildClass($concrete, $args);
    }

    throw new Error("无法识别的实现体类型：" . gettype($concrete), 500, "500:InvalidEntry");
  }

  /**
   * 校验工厂返回值确实是期望的类
   *
   * @param mixed $result 工厂返回值
   * @param string $expect 期望类名；空字符串表示不校验
   * @return object
   * @throws Error
   */
  protected function assertConcrete($result, $expect = "")
  {
    if (!is_object($result)) {
      throw new Error("工厂的返回值必须是对象，实际是 " . gettype($result), 500, "500:InvalidFactoryResult");
    }
    if ($expect !== "" && class_exists($expect) && !($result instanceof $expect)) {
      throw new Error(
        "工厂返回了 " . get_class($result) . "，与登记的 {$expect} 不符",
        500,
        "500:InvalidFactoryResult"
      );
    }

    return $result;
  }

  /**
   * 反射构造一个类，构造参数自动注入
   *
   * @param string $class 类名
   * @param array $args 调用方传入的参数
   * @return object
   * @throws Error
   */
  protected function buildClass($class, array $args = [])
  {
    $key = $this->normalize($class);

    if (isset($this->resolving[$key])) {
      $chain = implode(" → ", array_keys($this->resolving)) . " → {$class}";
      throw new Error("检测到循环依赖：{$chain}", 500, "500:CircularDependency");
    }

    $reflection = new ReflectionClass($class);
    if (!$reflection->isInstantiable()) {
      throw new Error("无法实例化 {$class}：抽象类或不可见构造函数", 500, "500:UninstantiableDependency");
    }

    $constructor = $reflection->getConstructor();

    $this->resolving[$key] = true;
    //* 构造期压入本容器所属应用：构造函数里 `$this->app()` 才能拿到正确归属
    //* （bindApp 在构造之后才生效，装配上下文是构造期唯一的来源）
    Applications::enterSetup($this->app);
    try {
      if ($constructor === null) {
        return new $class();
      }

      return $reflection->newInstanceArgs($this->resolveParameters($constructor, $args));
    } finally {
      Applications::leaveSetup();
      unset($this->resolving[$key]);
    }
  }

  /**
   * 解析一个可调用目标的参数（构造函数 / 方法 / 闭包共用）
   *
   * 取值优先级：
   * 1. 调用方传入：命名参数按名、位置参数按序；
   * 2. 类型命中：`Application` 注入本容器所属应用，`Request` 注入当前请求，其余类递归取用；
   * 3. 名字约定：`$app` / `$request`；
   * 4. 参数默认值；
   * 5. 允许 null 时给 null；
   * 6. 否则抛错。
   *
   * @param ReflectionFunctionAbstract $function 目标
   * @param array $args 调用方传入的参数
   * @return array 按顺序排好的参数列表
   * @throws Error
   */
  protected function resolveParameters(ReflectionFunctionAbstract $function, array $args = [])
  {
    $named = [];
    $positional = [];
    foreach ($args as $key => $value) {
      if (is_string($key)) {
        $named[$key] = $value;
      } else {
        $positional[] = $value;
      }
    }

    $arguments = [];
    foreach ($function->getParameters() as $index => $parameter) {
      $name = $parameter->getName();

      if (array_key_exists($name, $named)) {
        $arguments[] = $named[$name];
        continue;
      }
      if (array_key_exists($index, $positional)) {
        $arguments[] = $positional[$index];
        continue;
      }

      $type = $parameter->getType();
      $typeName = ($type instanceof ReflectionNamedType && !$type->isBuiltin()) ? $type->getName() : null;

      if ($typeName !== null) {
        if ($typeName === "self" || $typeName === "static") {
          $typeName = $function instanceof ReflectionMethod ? $function->getDeclaringClass()->getName() : $typeName;
        }
        if (is_a($typeName, Application::class, true)) {
          $arguments[] = $this->app;
          continue;
        }
        if (is_a($typeName, Request::class, true)) {
          $arguments[] = $this->app->request();
          continue;
        }
        if (class_exists($typeName)) {
          //* 依赖注入同样允许「没登记就构造」，否则普通类做参数时处处要登记
          $arguments[] = $this->make($typeName);
          continue;
        }
        if ($type->allowsNull()) {
          $arguments[] = null;
          continue;
        }

        throw new Error(
          "无法解析 {$this->describeCallable($function)} 的参数 \${$name}：{$typeName} 不可实例化且没有登记",
          500,
          "500:UnresolvableDependency"
        );
      }

      if ($name === "app") {
        $arguments[] = $this->app;
        continue;
      }
      if ($name === "request") {
        $arguments[] = $this->app->request();
        continue;
      }

      if ($parameter->isDefaultValueAvailable()) {
        $arguments[] = $parameter->getDefaultValue();
        continue;
      }
      if ($parameter->allowsNull()) {
        $arguments[] = null;
        continue;
      }

      throw new Error(
        "无法解析 {$this->describeCallable($function)} 的标量参数 \${$name}：请通过 make(名字, [\"{$name}\" => ...]) 传入",
        500,
        "500:UnresolvableDependency"
      );
    }

    return $arguments;
  }

  /**
   * 描述一个可调用目标（错误信息用）
   *
   * @param ReflectionFunctionAbstract $function 目标
   * @return string
   */
  protected function describeCallable(ReflectionFunctionAbstract $function)
  {
    if ($function instanceof ReflectionMethod) {
      return $function->getDeclaringClass()->getName() . "::" . $function->getName() . "()";
    }

    return "闭包";
  }

  // ==========================================================================
  //  内部工具
  // ==========================================================================

  /**
   * 算出登记名：给了别名就用别名，否则用实现体自带的类名
   *
   * @param mixed $concrete 实现体
   * @param string|null $name 调用方给的名字
   * @return string
   * @throws Error 闭包/工厂没给名字时抛出
   */
  protected function resolveName($concrete, $name = null)
  {
    if ($name !== null) {
      return ltrim((string) $name, "\\");
    }

    if (is_object($concrete) && !($concrete instanceof \Closure)) {
      return get_class($concrete);
    }

    if (is_string($concrete) && class_exists(ltrim($concrete, "\\"))) {
      return ltrim($concrete, "\\");
    }

    throw new Error(
      "登记闭包或 [类名, 方法] 工厂时必须给名字：provider(\$concrete, \"名字\")",
      500,
      "500:MissingEntryName"
    );
  }

  /**
   * 名字是否已被占用（四张表共用一个命名空间）
   *
   * @param string $key 归一化名
   * @param string $name 原始名（错误信息用）
   * @return void
   * @throws Error 已被登记时抛出
   */
  protected function assertNameFree($key, $name)
  {
    if (isset($this->providers[$key]) || isset($this->deferred[$key]) || isset($this->bindings[$key]) || isset($this->imports[$key])) {
      throw new Error(
        "名字 {$name} 已被登记，不能重复登记（同一名字只能有一种生命周期）",
        500,
        "500:DuplicateEntry"
      );
    }
  }

  /**
   * 把实例绑定到本容器所属应用
   *
   * 只在对象还没有归属时赋值：已有归属（别的应用构造的）保持不变，
   * 这样跨应用共享实例时不会把它「过户」到当前应用。
   *
   * @param mixed $object 候选对象
   * @return mixed 原对象
   */
  protected function bindApp($object)
  {
    if (!$object instanceof BaseObject) {
      return $object;
    }

    return $object->bindApp($this->app);
  }

  /**
   * 归一化名字：去前导反斜杠、转小写
   *
   * PHP 类名大小写不敏感，别名也不该区分大小写。
   *
   * @param string|object $abstract 名字或对象
   * @return string
   */
  protected function normalize($abstract)
  {
    $name = is_object($abstract) ? get_class($abstract) : (string) $abstract;

    return strtolower(ltrim($name, "\\"));
  }
}
