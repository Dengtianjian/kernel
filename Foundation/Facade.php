<?php

namespace kernel\Foundation;

/**
 * 门面基类
 *
 * 提供静态转发能力：所有静态调用将被转发到 accessor() 返回的底层实例的对应方法。
 *
 * 自动判定单例/多例（无需子类手动覆写 singleton()）：
 *   - 子类覆写 resolve()  →  单例门面：resolve() 提供兜底实例工厂，
 *                            accessor() 首次解析后缓存到 $registry，后续复用同一实例
 *   - 子类覆写 accessor() →  多例门面：每次转发都经该 accessor() 返回新实例
 *
 * 子类只需按需实现 resolve()（单例）或 accessor()（多例），无需再写 singleton()。
 *
 * 若底层实例无法解析（accessor() 返回 null），则对应静态调用返回 null，
 * 由子类决定是否在 accessor() 内抛出更明确的异常。
 */
abstract class Facade
{
  /** @var array<string,mixed> 门面实例存储，按门面类名隔离（key 为 static::class） */
  protected static array $registry = [];

  /**
   * 门面构造函数
   *
   * 实例化门面时按子类实现策略主动解析底层实例：
   *   - 子类覆写 resolve()（单例）→ 调用 resolve() 创建兜底实例；
   *   - 子类覆写 accessor()（多例）→ 调用 accessor() 获取实例；
   *   - 两者皆无 → 不执行任何解析（空构造）。
   *
   * 该构造仅为「实例化即预热」提供入口，静态调用路径（__callStatic）不受影响。
   */
  public function __construct()
  {
    if (self::hasOwnMethod("resolve")) {
      static::resolve();
    } elseif (self::hasOwnMethod("accessor")) {
      static::accessor();
    }
  }

  /**
   * 标识该门面是否为单例模式（自动检测，子类无需手动覆写）
   *
   * 判定规则：子类覆写 resolve() 即为单例，覆写 accessor() 即为多例，
   * 两者皆无则默认非单例。
   *
   * @return bool
   */
  /**
   * 判定子类是否自行声明（覆写）了指定方法
   *
   * 用于区分「基类默认实现」与「子类覆写实现」：仅当方法确实由子类
   * （而非 Facade 基类）声明时返回 true。
   *
   * @param string $name 方法名
   * @return bool
   */
  protected static function hasOwnMethod(string $name): bool
  {
    if (!method_exists(static::class, $name)) {
      return false;
    }
    return (new \ReflectionMethod(static::class, $name))->getDeclaringClass()->getName() !== self::class;
  }

  public static function singleton(): bool
  {
    return self::hasOwnMethod("resolve");
  }

  /**
   * 获取门面背后的底层实例
   *
   * 单例门面（子类覆写 resolve()）：首次调用 resolve() 创建并缓存到 $registry，
   * 后续复用同一实例。
   * 多例门面（子类覆写 accessor()）：由子类 accessor() 自行返回实例，
   * 本基类 accessor() 仅在非单例且未覆写时返回 null。
   *
   * @return object|null 实例无法解析时返回 null
   */
  protected static function accessor(): ?object
  {
    if (!static::singleton()) {
      return null;
    }
    $current = static::$registry[static::class] ?? null;
    if ($current === null) {
      $current = static::resolve();
      static::$registry[static::class] = $current;
    }
    return $current;
  }

  /**
   * 兜底实例工厂（仅单例门面需覆写）
   *
   * @return object
   */
  protected static function resolve(): object
  {
    throw new \BadMethodCallException(
      static::class . " is not a singleton facade; override resolve() to enable singleton mode."
    );
  }

  /**
   * 静态转发：将未定义的静态方法调用委托给底层实例
   *
   * @param string $method 方法名
   * @param array $arguments 参数列表
   * @return mixed
   */
  public static function __callStatic(string $method, array $arguments)
  {
    $instance = static::accessor();
    if ($instance === null) {
      return null;
    }
    return $instance->$method(...$arguments);
  }
}
