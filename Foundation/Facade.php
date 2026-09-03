<?php

namespace kernel\Foundation;

/**
 * 门面基类
 *
 * 提供静态转发能力：子类实现 accessor() 返回底层实例，
 * 所有静态调用将被转发到该实例的对应方法。
 *
 * 若底层实例无法解析（返回 null），则对应静态调用返回 null，
 * 由子类决定是否在 accessor() 内抛出更明确的异常。
 */
abstract class Facade
{
  /**
   * 获取门面背后的底层实例
   *
   * @return object|null 实例无法解析时返回 null
   */
  abstract protected static function accessor(): ?object;

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
