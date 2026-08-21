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
}
