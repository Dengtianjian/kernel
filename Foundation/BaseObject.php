<?php

namespace kernel\Foundation;

/**
 * 基对象，提供一些通用方法
 * @deprecated 请使用 Object 下的 BaseObject 对象
 */
class BaseObject
{
  /**
   * 单例实例
   *
   * @var static
   */
  private static $_singletons = [];
  /**
   * 单例调用
   * @param mixed ...$args 实例化时传入的参数
   *
   * @return static
   */
  final public static function singleton(...$args)
  {
    $className = get_called_class();
    if (!isset(self::$_singletons[$className])) {
      self::$_singletons[$className] = new static(...$args);
    }
    return self::$_singletons[$className];
  }
  /**
   * 快速实例化调用，该方法每次调用都会实例化一次类，如果要单例调用请使用 singleton 方法
   * @param mixed ...$args 实例化时传入的参数
   *
   * @return static
   */
  final public static function call(...$args)
  {
    return new static(...$args);
  }
}
