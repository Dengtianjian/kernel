<?php

use kernel\Foundation\Exception\Exception;

class Event
{
  /**
   * 已注册的事件
   *
   * @var array
   */
  static private $events = [];

  private $name = null;
  /**
   * 注册事件
   *
   * @param string $name 事件名称
   */
  public function __construct($name)
  {
    $this->name = $name;
    self::$events[$name] = [
      "instance" => $this,
      "subscriptions" => []
    ];
  }
  /**
   * 分发事件
   *
   * @param string $name 事件名称
   * @return Event
   */
  static function distribute($name)
  {
    if (!isset(self::$events[$name])) {
      throw new Exception("事件不存在或者未注册");
    }

    return self::$events[$name]['instance'];
  }
  /**
   * 触发事件
   *
   * @param string $name 事件名称
   * @param array ...$params 调用回调函数时传入的参数
   * @return Event
   */
  static function dispatch($name, ...$params)
  {
    if (!isset(self::$events[$name])) {
      throw new Exception("事件不存在或者未注册");
    }
    foreach (self::$events[$name]['subscriptions'] as $item) {
      call_user_func_array($item, $params);
    }

    return self::class;
  }
  /**
   * 订阅事件
   *
   * @param string $name 事件名称
   * @param callable $callback 事件触发时调用的回调函数
   * @return callable
   */
  static function subscribe($name, $callback)
  {
    if (!isset(self::$events[$name])) {
      throw new Exception("事件不存在或者未注册");
    }

    $Id = time() . count(self::$events[$name]['subscriptions']);
    self::$events[$name]['subscriptions'][$Id] = $callback;

    return function () use ($name, $Id) {
      unset(self::$events[$name]['subscriptions'][$Id]);
    };
  }
  /**
   * 一次性的订阅事件
   * 当事件触发后调用了传入的回调函数会自动取消掉订阅该事件
   *
   * @param string $name 事件名称
   * @param callable $callback 事件触发时调用的回调函数
   * @return Event
   */
  static function once($name, $callback)
  {
    if (!isset(self::$events[$name])) {
      throw new Exception("事件不存在或者未注册");
    }

    $Id = time() . count(self::$events[$name]['subscriptions']);
    self::$events[$name]['subscriptions'][$Id] = function () use ($name, $Id, $callback) {
      $callback();
      unset(self::$events[$name]['subscriptions'][$Id]);
    };

    return self::class;
  }
}
