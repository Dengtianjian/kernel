<?php

namespace kernel\Foundation;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 服务基础对象
 *
 * 服务（Service）通常作为「无状态的静态功能类」使用：子类仅提供静态方法，
 * 直接以 `XxxService::method()` 调用，并可通过继承的 AbilityBaseObject 错误机制
 * （setError / break / forwardBreak / return）返回错误结果。
 *
 * 本类不持有实例状态，也未定义构造逻辑；`bootstrap()` / `bootUp()` 是供子类
 * 覆盖的静态扩展点（钩子），基类为空实现。
 */
class Service extends AbilityBaseObject
{
  /**
   * 装配服务（静态扩展钩子）
   *
   * 供子类覆盖，例如注册服务所需的路由、注入依赖、装配 SDK、配置等。
   * 在应用启动阶段调用一次。基类为空实现，仅作为约定入口。
   *
   * @return void
   */
  public static function bootstrap()
  {
  }

  /**
   * 启动就绪（静态扩展钩子）
   *
   * 供子类覆盖，例如初始化存储结构、建立连接、加载配置等，在 `bootstrap()`
   * 之后完成一次性的启动准备。基类为空实现，仅作为约定入口。
   *
   * @return void
   */
  public static function bootUp()
  {
  }
}
