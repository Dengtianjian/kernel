<?php

namespace kernel\Foundation\Object;

/**
 * 基对象，提供通用的实例化与单例能力
 *
 * 设计约定（子类与调用方务必知晓）：
 * - 单例缓存按 `get_called_class()`（后期静态绑定的实际类名）分类存放，
 *   互不共享、也不随继承传递——每个具体子类各持有一份单例。
 * - 单例「只认类型、不认参数」：构造参数仅在首次实例化时生效。
 *   首次实例化后，无参调用 `singleton()` 是取缓存的常规用法，不受影响；
 *   若之后又以**不同的非空参数**调用 `singleton()`，会抛出 `LogicException`
 *   以提示调用方，避免静默拿到旧实例。
 * - 单例不可通过 `clone` 或反序列化绕过唯一性（已私有化 `__clone`、
 *   并令 `__wakeup` 抛异常）。
 */
class BaseObject
{
  /**
   * 本对象所属的应用
   *
   * 由构造期注入（{@see resolveApp()}），不做事后推断：对象一旦创建，归属就固定，
   * 不会因为「现在哪个应用在跑」而变。未注入时由 {@see app()} 回落到默认应用。
   *
   * 注意：`Modules\WechatPay\V3\WechatPayV3` 已有同名 `app()`（返回 APP 支付产品），
   * 它会覆盖本方法——那个类不参与应用归属解析。新增子类时请避免再用 `app` 作为方法名。
   *
   * @var \kernel\Foundation\App|null
   */
  protected $app = null;
  /**
   * 单例实例池，键为实际类名（get_called_class()）
   *
   * @var array<string, static>
   */
  private static $singletonPool = [];
  /**
   * 单例首次实例化时的构造参数指纹，键为实际类名
   *
   * 值为 null 表示参数无法序列化（如闭包、资源），跳过一致性检测。
   *
   * @var array<string, string|null>
   */
  private static $singletonFingerprints = [];

  /**
   * 防止克隆破坏单例唯一性
   */
  private function __clone()
  {
  }

  /**
   * 防止反序列化重建实例破坏单例唯一性
   *
   * @throws \LogicException
   */
  public function __wakeup()
  {
    throw new \LogicException("单例类不允许被反序列化");
  }

  /**
   * 单例调用：每个类仅实例化一次，后续调用返回缓存实例
   *
   * 注意：单例只认类型、不认参数。参数仅在首次实例化时生效；
   * 首次实例化后的无参调用直接返回缓存实例；
   * 若之后以不同的非空参数调用，会抛出 `LogicException`。
   *
   * @param mixed ...$args 首次实例化时传入的构造参数
   * @return static
   * @throws \LogicException 当以不同参数重复实例化单例时
   */
  final public static function singleton(...$args)
  {
    $className = get_called_class();
    if (isset(self::$singletonPool[$className])) {
      // 无参调用是取缓存的常规用法，不参与参数一致性校验
      if ($args !== []) {
        $newFp = self::buildFingerprint($args);
        $oldFp = self::$singletonFingerprints[$className];
        if ($oldFp !== null && $newFp !== null && $oldFp !== $newFp) {
          throw new \LogicException(
            sprintf('%s::singleton() 已用不同构造参数实例化，请勿重复传入不同的参数', $className)
          );
        }
      }
      return self::$singletonPool[$className];
    }

    $instance = new static(...$args);
    self::$singletonPool[$className] = $instance;
    self::$singletonFingerprints[$className] = self::buildFingerprint($args);

    return $instance;
  }

  /**
   * 工厂调用：每次调用都会实例化一次类
   *
   * 需要单例时请使用 {@see singleton()} 方法。
   *
   * @param mixed ...$args 实例化时传入的参数
   * @return static
   */
  final public static function make(...$args)
  {
    return new static(...$args);
  }

  /**
   * 判断某个类是否已经完成单例实例化
   *
   * @param string|null $class 要查询的类名，为空时使用调用者类名
   * @return boolean
   */
  final public static function hasSingleton($class = null)
  {
    $class = $class ?: get_called_class();
    return isset(self::$singletonPool[$class]);
  }

  /**
   * 清空单例缓存，便于测试重置或释放常驻进程中的实例
   *
   * @param string|null $class 要清除的类名，为空时清空全部单例缓存
   * @return void
   */
  final public static function clearSingleton($class = null)
  {
    if (is_null($class)) {
      self::$singletonPool = [];
      self::$singletonFingerprints = [];
      return;
    }
    unset(self::$singletonPool[$class], self::$singletonFingerprints[$class]);
  }

  /**
   * 生成构造参数的指纹，用于单例参数一致性检测
   *
   * 参数无法序列化（如闭包、资源、含闭包的对象）时返回 null，
   * 表示跳过一致性检测，保证兼容性与向后兼容。
   *
   * @param array $args 构造参数
   * @return string|null
   */
  private static function buildFingerprint(array $args)
  {
    try {
      return serialize($args);
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * 本对象所属的应用
   *
   * 优先返回构造期注入的实例；未注入时回落到默认应用（= 当前请求的应用）。
   * 两者都没有说明引导阶段就有对象在算表名 / 路径，属于装配错误——直接抛错，
   * 让问题在 `new` 的那一刻暴露，而不是把表名拼成 `_files` 这种错得无声无息的结果。
   *
   * @return \kernel\Foundation\App
   * @throws \kernel\Foundation\Exception\Error 未关联任何应用时抛出
   */
  public function app()
  {
    $app = $this->app
      ?: \kernel\Foundation\Application\Applications::setUpApp()
      ?: \kernel\Foundation\Application\Applications::default();

    if ($app === null) {
      throw new \kernel\Foundation\Exception\Error(
        static::class . " 未关联任何应用：请先注册应用（new DiscuzXApp(null, \$pluginId)），或用 \$app->make() 创建对象",
        500,
        "500:AppNotRegistered"
      );
    }

    return $app;
  }

  /**
   * 补归属应用（容器专用）
   *
   * 只在对象自身还没有归属时补；已有归属（构造签名里写了 `App` 的）原样保留。
   *
   * 读与写都在本类内完成——`Container` 从外部读写 protected 的 `$app` 会直接报
   * `Cannot access non-public member`，所以这里开一个受控入口，而不是让容器玩反射。
   *
   * @internal 仅供 {@see \kernel\Foundation\Application\Container::bindApp()} 在
   *           构造完对象后调用；业务代码请勿使用（改归属会造出「半个对象属于上一个应用」的东西）
   * @param \kernel\Foundation\App|null $app 所属应用；null 表示交给默认应用
   * @return $this
   */
  public function bindApp($app)
  {
    if ($this->app === null) {
      $this->app = $app;
      $this->onAppBound();
    }

    return $this;
  }

  /**
   * 归属绑定完成的钩子
   *
   * 容器是「先构造、后补归属」，构造期只能按默认应用推导的东西（典型如表名前缀）
   * 需要在归属就位后重算。默认空实现，需要重算的子类覆盖本方法。
   *
   * 只在 `bindApp()` 真正赋值时触发一次；对象已有归属时不会再调。
   *
   * @return void
   */
  protected function onAppBound()
  {
  }

  /**
   * 构造期注入所属应用
   *
   * protected 而非 public：表名、路径、签名密钥都在构造期按 app 算好了，
   * 事后改只会造出「半个对象属于上一个应用」的东西。
   *
   * @internal 仅供构造链调用
   * @param \kernel\Foundation\App|null $app 所属应用；null 表示交给默认应用
   * @return void
   */
  protected function resolveApp($app = null)
  {
    $this->app = $app;
  }
}
