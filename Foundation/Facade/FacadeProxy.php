<?php

namespace kernel\Foundation\Facade;

/**
 * 门面代理（FacadeProxy）
 *
 * 把「任意类」包装成可静态式调用的门面：调用方无需手动 new，即可通过代理
 * 直接调用目标类的实例方法，底层自动单例化（同一类名首次实例化后复用）。
 *
 * 由 {@see Facade::of()} 创建，典型用法：
 * ```php
 * Facade::of(SomeService::class)->doSomething($a, $b);
 * Facade::of(SomeService::class, $dep)->doSomething(); // 构造参数仅首次实例化生效
 * ```
 *
 * 语义与 {@see \kernel\Foundation\Object\BaseObject::singleton()} 对齐：
 * - 单例按「目标类名」隔离，互不共享；
 * - 构造参数仅在首次实例化时生效；之后以不同非空参数再次调用会抛异常，
 *   避免静默拿到旧实例。
 *
 * 目标类无需继承任何基类，也不需要是 static 方法——代理统一转发到实例方法上。
 */
class FacadeProxy
{
  /** @var string 目标类名（完整命名空间） */
  protected $class;

  /** @var array 目标类构造参数（仅首次实例化生效） */
  protected $args;

  /** @var array<string, object> 目标实例单例池，键为类名 */
  private static $instances = [];

  /** @var array<string, string|null> 首次实例化的构造参数指纹，键为类名 */
  private static $fingerprints = [];

  /**
   * @param string $class 目标类名（完整命名空间）
   * @param array  $args  目标类构造参数（仅首次实例化生效）
   */
  public function __construct(string $class, array $args = [])
  {
    $this->class = $class;
    $this->args = $args;
  }

  /**
   * 解析目标实例（惰性单例）
   *
   * 首次调用时 `new $class(...$args)` 并缓存；后续复用同一实例。
   * 若以「不同的非空构造参数」重复请求同一类，抛出 LogicException。
   *
   * @return object 目标实例
   * @throws \LogicException 参数指纹不一致时
   */
  protected function instance()
  {
    if (isset(self::$instances[$this->class])) {
      // 已有实例时校验构造参数一致性（无参调用跳过，属取缓存常规用法）
      if ($this->args !== []) {
        $newFp = $this->fingerprint($this->args);
        $oldFp = self::$fingerprints[$this->class] ?? null;
        if ($oldFp !== null && $newFp !== null && $oldFp !== $newFp) {
          throw new \LogicException(
            sprintf('%s 已用不同构造参数实例化，请勿重复传入不同的参数', $this->class)
          );
        }
      }
      return self::$instances[$this->class];
    }

    $instance = new ($this->class)(...$this->args);
    self::$instances[$this->class] = $instance;
    self::$fingerprints[$this->class] = $this->fingerprint($this->args);

    return $instance;
  }

  /**
   * 实例方法转发
   *
   * @param string $method 方法名
   * @param array  $arguments 参数列表
   * @return mixed
   */
  public function __call(string $method, array $arguments)
  {
    return $this->instance()->$method(...$arguments);
  }

  /**
   * 属性读取透传（返回目标实例的公开/动态属性）
   *
   * @param string $name 属性名
   * @return mixed
   */
  public function __get(string $name)
  {
    return $this->instance()->$name;
  }

  /**
   * 属性写入透传
   *
   * @param string $name 属性名
   * @param mixed  $value 值
   * @return void
   */
  public function __set(string $name, $value): void
  {
    $this->instance()->$name = $value;
  }

  /**
   * 生成构造参数指纹（用于一致性校验）
   *
   * 参数无法序列化（闭包、资源等）时返回 null，跳过校验。
   *
   * @param array $args 构造参数
   * @return string|null
   */
  private function fingerprint(array $args): ?string
  {
    try {
      return serialize($args);
    } catch (\Throwable $e) {
      return null;
    }
  }
}
