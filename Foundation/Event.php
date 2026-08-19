<?php

namespace kernel\Foundation;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Log;

class Event
{
  /**
   * 已注册的事件实例（进程内注册表）
   *
   * @var array<string, Event>
   */
  static private $events = [];
  /**
   * 事件名称
   *
   * @var string
   */
  private $name = "";
  /**
   * 订阅者列表
   *
   * 每个订阅者可为下列三种形式之一：
   * - 类名字符串（如 "Foo"）：实例化即处理（new Foo(...$params)），兼容旧写法
   * - [类名, 方法名]（如 [Foo::class, "handle"]）：实例化后调用方法
   * - 可调用（闭包 / 函数 / __invoke 对象）：直接调用
   *
   * @var array
   */
  private $subscriptions = [];

  /**
   * 注册事件
   *
   * 构造即注册到进程内注册表；同名事件重复注册会覆盖已存在的订阅者列表。
   *
   * @param string $name 事件名称
   * @param array $subscriptions 订阅者数组
   */
  public function __construct($name, $subscriptions = [])
  {
    $this->name = $name;
    $this->subscriptions = $subscriptions;

    self::$events[$name] = $this;
  }

  /**
   * 获取事件名称
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * 获取订阅者列表
   *
   * @return array
   */
  public function getSubscriptions()
  {
    return $this->subscriptions;
  }

  /**
   * 检查事件是否已注册
   *
   * @param string $name 事件名称
   * @return bool
   */
  static function has($name)
  {
    return isset(self::$events[$name]);
  }

  /**
   * 移除已注册的事件
   *
   * @param string $name 事件名称
   * @return bool 事件不存在时返回 false
   */
  static function remove($name)
  {
    if (!isset(self::$events[$name])) {
      return false;
    }
    unset(self::$events[$name]);

    return true;
  }

  /**
   * 获取事件分发闭包
   *
   * 事件是否存在的校验延迟到闭包被调用时（由 dispatch 完成），
   * 因此闭包可在事件注册前创建，注册后再调用。
   *
   * @param string $name 事件名称
   * @return callable 调用该闭包时触发事件
   */
  static function distribute($name)
  {
    return function (...$params) use ($name) {
      self::dispatch($name, ...$params);
    };
  }

  /**
   * 触发事件
   *
   * @param string $name 事件名称
   * @param array ...$params 传给订阅者的参数
   * @return string 事件类名（kernel\Foundation\Event）
   */
  static function dispatch($name, ...$params)
  {
    if (!isset(self::$events[$name])) {
      throw new Exception("事件不存在或者未注册");
    }
    self::$events[$name]->send($params);

    return self::class;
  }

  /**
   * 发送订阅通知
   *
   * 依次执行每个订阅者；单个订阅者抛出的异常会被捕获记录，
   * 不影响其余订阅者的执行。
   *
   * @param array $params 发送通知的参数
   * @return void
   */
  private function send($params)
  {
    foreach ($this->subscriptions as $item) {
      try {
        $this->invokeSubscriber($item, $params);
      } catch (\Throwable $e) {
        Log::error("事件订阅者执行失败", [
          "event" => $this->name,
          "subscriber" => $this->describeSubscriber($item),
          "message" => $e->getMessage()
        ]);
      }
    }
  }

  /**
   * 执行单个订阅者
   *
   * @param mixed $item 订阅者（类名 / [类名, 方法名] / 可调用）
   * @param array $params 传给订阅者的参数
   * @return void
   */
  private function invokeSubscriber($item, $params)
  {
    if (is_array($item)) {
      //* [类名, 方法名]：实例化后调用方法
      list($class, $method) = $item;
      (new $class())->$method(...$params);

      return;
    }
    if (is_callable($item)) {
      //* 闭包 / 函数 / __invoke 对象：直接调用
      $item(...$params);

      return;
    }
    //* 类名字符串：实例化即处理（兼容旧写法）
    new $item(...$params);
  }

  /**
   * 描述订阅者（用于异常日志）
   *
   * @param mixed $item 订阅者
   * @return string
   */
  private function describeSubscriber($item)
  {
    if (is_object($item)) {
      return get_class($item);
    }
    if (is_array($item)) {
      return implode("::", $item);
    }

    return (string)$item;
  }
}
