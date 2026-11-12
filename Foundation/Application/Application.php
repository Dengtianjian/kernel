<?php

namespace kernel\Foundation\Application;

use kernel\Foundation\Builtin\Config;
use kernel\Foundation\Data\Date;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\Path;

/**
 * 应用基类：身份 + 容器 + 组件装配
 *
 * 一个应用就是「一个 ID + 一个容器」，本类只回答这两件事：
 * 我是谁（{@see appId()}）、我有哪些组件（`register` / `bind` / `import` / `make` / `$app->xxx()`）。
 * 与请求、路由、响应有关的执行流程不在这里——那是子类 {@see \kernel\Foundation\App} 的事。
 *
 * 之所以拆成两层：这十几个方法对 HTTP 零依赖，`new` 完不开请求就能单测；而 Web 那一层
 * 可以整体替换，将来要 console 应用或队列 worker，继承本类即可，不必再拖一份路由代码。
 *
 * 组件一律由容器持有，本类不为组件保留属性；`$app->xxx()` 由 {@see __call()}
 * 按类短名解析到容器。新增组件只需在装配期绑定，不需要改这个类。
 *
 * @see Container 绑定与解析
 * @see __call() 短名到容器的解析规则
 */
abstract class Application
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
   * 组件容器（内部件）
   *
   * 业务不直接接触：一律走本类上的 register / bind / make / import，
   * 由这些方法代理到容器。拆成独立类是为了本类不再膨胀、容器可脱离 HTTP 单独单测。
   *
   * @var Container|null
   */
  protected $container = null;
  /**
   * 应用启动时间（毫秒时间戳），响应里算 requiredTime 用
   *
   * @var int|null
   */
  protected $startTime = null;
  /**
   * 是否已完成装配（Bootstrap 已执行）
   *
   * 与「是否已登记」是两回事：{@see of()} 懒装配出来的应用只建骨架、不跑 Bootstrap，
   * 状态是「已登记但未装配」。跨应用借用（`import`）前必须确认提供方**已装配**，
   * 否则它的容器里没有任何业务组件。
   *
   * @var bool
   */
  protected $assembled = false;
  protected function __clone() {}
  /**
   * 构建 App：应用基座
   *
   * 只做基座准备（记录身份、登记到应用管理器、建容器、记启动时间），**不构造任何组件实例**：
   * 组件首次 `$app->x()` 才反射实例化，因此 {@see of()} 懒装配别的应用时
   * 不会顺带把对方的请求、路由表、钩子链拉起来。
   *
   * 没有独立的 `boot()` 步骤：基座就是构造函数本身，子类要追加初始化就覆盖构造函数、
   * 先调 `parent::__construct()`（见 {@see \kernel\Foundation\App}）。
   * 少一层间接调用，也避免「构造完还得记得再 boot 一次」这种半初始化的对象状态。
   *
   * 要换掉某个组件实现，装配类里 `$app->register($custom)` 即可——容器里的实例优先。
   *
   * @param string $id 设定一个 APP 唯一标识，跟项目目录同名
   * @param string $kernelId 修改内核默认 id。内核也是一个 APP，所以也有 ID，默认是 kernel
   */
  function __construct($id, $kernelId = "kernel")
  {
    $this->id = $id;
    $this->kernelId = $kernelId;

    //* 登记到应用管理器：首个注册者自动成为默认应用
    Applications::register($this, $id);

    include_once(Path::join(Path::kernel() . "/Foundation/Common.php"));

    //* 容器：此后 register() / make() / import() 均可直接用
    $this->container = new Container($this);

    //* 启动时间：响应输出时靠它算 requiredTime。
    //* 刻意不在这里读配置文件——配置里可能有 `if (!defined("IN_DISCUZ")) exit;` 这类
    //* 宿主保护，CLI / 单测环境提前 include 会直接退出；配置留给首次 `config()` 访问时加载。
    $this->startTime = Date::milliseconds();
  }

  // ==========================================================================
  //  身份与运行模式
  // ==========================================================================

  /**
   * 本实例的应用 ID
   *
   * 回答「我这个应用是谁」——多应用共存时只有它是可靠的。静态上下文中要取
   * 「当前默认应用是谁」请用 {@see Applications::defaultId()}。
   *
   * 服务 / 上下文里要记「这条数据属于哪个应用」，一律用本方法，不要用静态的。
   *
   * @return string|null
   */
  public function appId()
  {
    return $this->id;
  }
  /**
   * 本应用是否已完成装配
   *
   * `true` 表示装配类（Bootstrap）已经跑过、业务组件已进容器；
   * `false` 有两种可能：还没装配（正在构造）、或只是 {@see of()} 建出来的骨架。
   *
   * 跨应用 import 前应确认提供方为 `true`，否则取用时会报「没有登记」。
   *
   * @return bool
   */
  public function assembled()
  {
    return $this->assembled;
  }
  /**
   * 应用启动时间（毫秒时间戳）
   *
   * 在构造函数里写入，比「读配置的 startTime」可靠：配置可能压根没加载。
   *
   * @return int|null
   */
  public function startTime()
  {
    return $this->startTime;
  }
  /**
   * 获取当前运行模式（mode）
   *
   * 读取默认应用配置的 "mode" 键（`$app->config()->get("mode", "production")`），未配置时默认 "production"。
   * 替代原 F_APP_MODE 常量，无需提前定义，随时可安全调用。
   *
   * 静态上下文用本方法；手上已有应用实例时用 {@see isDevelopment()} / {@see isProduction()}，
   * 那两个读的是**该实例自己**的配置（多应用可以是不同模式）。
   *
   * @return string 运行模式（production / development / local / release 等）
   */
  public static function mode(): string
  {
    //* 静态入口留给「没有 $app 上下文」的地方（自动加载器、视图）。
    //* 只读**已经加载过**的配置，不在这里触发加载：自动加载是每条 include 都会走的热路径，
    //* 让它去读磁盘（甚至 include 带 IN_DISCUZ 保护的配置文件）会把 CLI 直接带崩。
    //* 配置还没加载时按 production 处理——保守取向，与「配置缺失」一致。
    $app = Applications::default();
    $config = $app === null ? null : $app->resolvedConfig();

    return $config === null ? "production" : $config->get("mode", "production");
  }
  /**
   * 本应用是否开发模式（`mode === "development"`，字面量比较）
   *
   * 读本实例所属应用的配置，"mode" 缺失时按 `production`：保守取向，不能因为配置缺失就把调试详情暴露出去。
   *
   * @return bool
   */
  public function isDevelopment(): bool
  {
    return $this->config()->get("mode", "production") === "development";
  }
  /**
   * 本应用是否生产模式（`mode === "production"`，字面量比较）
   *
   * 配置缺失也算生产模式；要「非开发即生产」的宽松判定请写 `!$app->isDevelopment()`。
   *
   * @return bool
   */
  public function isProduction(): bool
  {
    return $this->config()->get("mode", "production") === "production";
  }
  /**
   * 已加载的配置实例（**不触发加载**）
   *
   * {@see config()} 会构建并读文件；本方法只回答「已经加载了没有」。
   * 给静态入口（{@see mode()}）和排查用。
   *
   * @return \kernel\Foundation\Builtin\Config|null
   */
  public function resolvedConfig()
  {
    //* 容器不再提供「只查已构造实例」的入口，这里扫一遍已构造的单例即可
    foreach ($this->container()->providers() as $instance) {
      if ($instance instanceof \kernel\Foundation\Builtin\Config) {
        return $instance;
      }
    }

    return null;
  }

  // ==========================================================================
  //  登记与解析（委托 Container，对外只有 Application 这一层）
  // ==========================================================================

  /**
   * 取内部容器（实现细节）
   *
   * 业务代码请直接调用 Application / App 上的 register / bind / make / import，
   * 不要通过本方法绕行——容器是内部件，不作为第二个对外概念存在。
   *
   * @internal
   * @return Container
   */
  public function container()
  {
    if ($this->container === null) {
      $this->container = new Container($this);
    }

    return $this->container;
  }

  /**
   * 登记一个单例组件（装配期调用）
   *
   * 传类名则立即反射构造（构造参数按类型自动注入），传实例则直接登记；
   * 继承 {@see Context} 的进请求级表（`flush()` 会清），其余进应用级表。
   *
   * 支持数组批量登记：`register([A::class, "alias" => B::class])`。
   *
   * @param object|string|array $component 组件实例、类名或它们的数组
   * @param string|null $alias 取用别名（`$app->xxx()` / `make("xxx")`）
   * @return $this
   */
  public function provider($concrete, $name = null)
  {
    //* 批量登记：provider([$a, $b]) —— 元素都是实例或类名时按数组处理，
    //* `[类名, 方法]` 工厂因为第二个元素不是类名，不会被误判成批量
    if ($name === null && is_array($concrete) && $concrete !== []) {
      $isBatch = true;
      foreach ($concrete as $item) {
        if (!is_object($item) && !(is_string($item) && class_exists($item))) {
          $isBatch = false;
          break;
        }
      }
      if ($isBatch) {
        foreach ($concrete as $item) {
          $this->container()->provider($item);
        }

        return $this;
      }
    }

    $this->container()->provider($concrete, $name);

    return $this;
  }

  /**
   * 登记延迟单例
   *
   * 与 {@see provider()} 相同，只是强调「首次取用才构造」；传实例时退化为单例。
   *
   * @param object|string|callable|array $concrete 实现体
   * @param string|null $name 取用名；省略时按实现体推导
   * @return $this
   */
  public function defer($concrete, $name = null)
  {
    $this->container()->defer($concrete, $name);

    return $this;
  }

  /**
   * 该名字是否已登记（不论有没有构造）
   *
   * @param string $abstract 类名或别名
   * @return bool
   */
  public function has($abstract)
  {
    return $this->container()->has($abstract);
  }

  /**
   * 登记一个多例类
   *
   * 第一个参数永远是类名，第二个参数可选地给它起个短名；不传短名时自动取类短名。
   * 登记后 `make("短名")` / `make(类名)` 都可用，每次都是新对象，从不缓存。
   *
   * @param string $class 类名
   * @param string|null $alias 取用短名；省略时取类短名
   * @return $this
   */
  public function bind($concrete, $name = null)
  {
    $this->container()->bind($concrete, $name);

    return $this;
  }

  /**
   * 解析实例
   *
   * 顺序：已登记单例 → 多例别名 → 跨应用来源 → 反射构造。
   * 反射构造出来的一次性对象**不缓存**；要共享就先 `register()`。
   *
   * @param string|object $abstract 类名、别名或实例
   * @param array $params 标量参数（参数名 => 值）
   * @return mixed
   */
  public function make($abstract, array $params = [])
  {
    return $this->container()->make($abstract, $params);
  }



  /**
   * 声明「本应用的某个能力来自另一个应用」
   *
   * 装配期一行，之后照旧 `make()`。记 appId 不记实例：时序无关、支持懒启动、双向对称。
   * 不传 appId 时按类名根命名空间推导，`kernel\` 下的类必须显式传。
   * 第三个参数给导入起别名，之后 `$app->别名()` / `make("别名")` 都能取到。
   *
   * @param string $abstract 抽象名（类名）
   * @param string|null $appId 提供方应用 ID
   * @param string|null $alias 取用别名
   * @return $this
   */
  public function import($abstract, $appId = null, $alias = null)
  {
    $this->container()->import($abstract, $appId, $alias);

    return $this;
  }

  /**
   * 本应用引入了哪些外部能力（排查用）
   *
   * @internal 依赖图 dump（{@see Applications::graph()}）用，业务不要靠它做判断
   * @return array<string, string> 抽象名 => 提供方 appId
   */
  public function imports()
  {
    return $this->container()->imports();
  }

  // ==========================================================================
  //  排查入口
  // ==========================================================================

  /**
   * 已构造的单例（排查用）
   *
   * @return array<string, object> 名字 => 实例
   */
  public function providers()
  {
    return $this->container()->providers();
  }

  // ==========================================================================
  //  取用入口
  // ==========================================================================

  /**
   * 组件名访问：`$app->request()` / `$app->config()` / `$app->settingProvider()` …
   *
   * 只查**已登记的单例**（`providers` → `contexts`），短名大小写不敏感、也接受完整类名，
   * 必须唯一命中。不做反射兜底：拼错、没登记、重名都直接抛错。
   *
   * 要「每次新建」的对象不登记，用 `bind()` 起别名后走 `make("别名")`——
   * `__call` 不碰多例，避免同一个方法名有时同一份、有时新的。
   *
   * @param string $name 组件短名或完整类名
   * @param array $arguments 不接受参数；需要传参请用 `make(类名, [...])`
   * @return mixed 组件实例
   * @throws Error 传参 / 未登记 / 短名歧义时抛出
   */
  public function __call($name, $arguments)
  {
    return $this->container()->make($name, $arguments);
  }

  // ==========================================================================
  //  跨应用与实例管理
  // ==========================================================================

  /**
   * 本实例的内核 ID（KernelId），即内核目录文件夹名称
   *
   * 就是构造时传入的第二个参数（DiscuzX 下默认 `gstudio_kernel`，其余默认 `kernel`）。
   * 多应用共存时只有它是可靠的——静态语境要取「默认应用的内核 ID」，
   * 请写 `Applications::default()->kernelId()`，不要用静态的。
   *
   * @return string|null
   */
  public function kernelId()
  {
    return $this->kernelId;
  }

  /**
   * 按应用 ID 或该类所属的任意类名，取对方的应用实例
   *
   * 与「隐式回落」的区别：必须写出要访问谁（ID 或类名），取不到直接抛错。
   *
   * @param string $idOrClass 应用 ID 或该应用的任意类名
   * @return Application
   * @throws Error 无法确定应用 ID，或该应用尚未登记时抛出
   */
  public static function of($idOrClass)
  {
    $target = is_object($idOrClass) ? get_class($idOrClass) : (string) $idOrClass;
    //* 含命名空间分隔符即视为类名（按根命名空间推导 appId），否则视为 appId 本身。
    //* `class_exists($target, false)` 刻意不触发自动加载：`$target` 可能是纯 appId（如
    //* "gstudio_super_app"），一旦触发 Discuz 的 core::autoload，带下划线的名字会被
    //* core::import 误判为类文件路径，直接 break 输出「Oops! System file lost」。
    $isClassName = is_object($idOrClass) || strpos($target, "\\") !== false || class_exists($target, false);
    $appId = $isClassName ? static::owner($target) : ltrim($target, "\\");

    if ($appId === null || $appId === "") {
      throw new Error("无法确定应用 ID：{$target}", 500, "500:UnknownApp");
    }

    $app = Applications::get($appId);
    if ($app === null) {
      //* 懒装配：按默认应用的实际类现建一个（走完整构造，与先建的那个同类）。
      //* 构造只登记框架零件，业务组件在装配类里注册——所以懒装配出来的应用只有骨架，
      //* 跨应用要取业务能力，得由使用方自己 register() 或 make() 多例。
      $class = static::instanceClass();
      $default = Applications::default();
      //* 第一个参数恒为装配（null = 只建骨架），appId / kernelId 排在其后
      new $class(null, $appId, $default === null ? null : $default->kernelId());
      $app = Applications::get($appId);
    }

    if ($app === null) {
      throw new Error("应用 {$appId} 无法登记，无法取用其能力", 500, "500:UnknownApp");
    }

    return $app;
  }

  /**
   * 懒装配新应用时使用的类
   *
   * 取当前默认应用的实际类（DiscuzX 下即 {@see \kernel\DiscuzX\Foundation\DiscuzXApp}），
   * 保证懒装配出来的应用与先建的那个同类；进程里一个应用都没有时退回本类。
   *
   * @return class-string<Application>
   */
  protected static function instanceClass()
  {
    $default = Applications::default();

    return $default === null ? static::class : get_class($default);
  }

  /**
   * 类所归属的应用 ID
   *
   * 依据是类型的根命名空间（等于插件目录名），与「当前在跑哪个应用」无关。
   * `kernel\` 是无归属的共用代码，返回 null——此时跨应用调用必须显式写 appId。
   *
   * @param object|string $classOrObject 对象、类名或命名空间字符串
   * @return string|null
   */
  public static function owner($classOrObject)
  {
    $appId = Identity::fromNamespace($classOrObject);

    //* kernel 在内核目录下、DiscuzX 下会被补成 gstudio_kernel —— 两者都是「无归属」的共用代码
    if (!$appId || $appId === "kernel" || $appId === "gstudio_kernel") {
      return null;
    }

    return $appId;
  }
}
