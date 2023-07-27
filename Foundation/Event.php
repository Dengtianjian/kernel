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
  private $subscriptions = [];
  /**
   * 注册事件
   *
   * @param string $name 事件名称
   */
  public function __construct($name, $subscriptions = [])
  {
    $this->name = $name;
    $this->subscriptions = $subscriptions;

    self::$events[$name] = $this;
  }
  /**
   * 分发事件
   *
   * @param string $name 事件名称
   * @return callable
   */
  static function distribute($name)
  {
    if (!isset(self::$events[$name])) {
      throw new Exception("事件不存在或者未注册");
    }

    return function (...$params) use ($name) {
      self::dispatch($name, ...$params);
    };
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
    self::$events[$name]->send($params);

    return self::class;
  }
  private function send($params)
  {
    foreach ($this->subscriptions as $item) {
      new $item(...$params);
    }
  }
}
