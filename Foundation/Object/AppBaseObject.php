<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\App;

/**
 * 应用感知基础对象（AppBaseObject）
 *
 * 为所有「业务可装配实体」（模型 / 模块 / 服务 / 存储 / 控制器等）提供统一的
 * 「应用归属」感知能力，解决子系统复用主系统类时的 app 归属问题：
 *
 * 在 `new App(...)->setup(...)` 生命周期内实例化、注册的类，都属于「当前正在装配
 * 的应用」；即便在 download 进程里实例化 super_app 的 setup 类，只要装配上下文正确，
 * 这些类拿到的 app 就是 super_app —— 等价于两个应用各自在跑。
 *
 * 提供两个维度的归属查询（各取所需）：
 * - {@see app()}  ：返回「当前默认应用」实例（装配上下文，经 {@see getApp()}）；
 *   表示「这一段装配是在为哪个应用服务」。
 * - {@see appId()}：返回「类自身所属应用」的 ID（经 {@see App::appIdFromNamespace(static::class)}）；
 *   表示「这个类定义在哪个插件」，与运行时的装配上下文无关。
 *
 * 继承关系：BaseObject → AppBaseObject → AbilityBaseObject →（Table/Model/Module/Service/...）
 * 所有走 AbilityBaseObject 链的基类自动获得 app()/appId()；Controller 直接继承本类。
 */
class AppBaseObject extends BaseObject
{
  /**
   * 获取当前默认应用实例（装配上下文）
   *
   * 返回 {@see getApp()}（即 {@see \kernel\Foundation\Application\Applications::default()}）。
   * 在 setup 生命周期内，它就是「正在装配的应用」；未登记任何应用时返回 null。
   *
   * @return App|null 默认应用实例；无可用实例时返回 null
   */
  public function app()
  {
    return getApp();
  }

  /**
   * 获取本实例所属应用的 ID（类归属）
   *
   * 归属由「类自身命名空间第一段」决定，而非运行时默认应用。保证子系统复用主系统
   * 类时（download 进程里 new 了 super_app 的模型/服务/模块），归属仍指向类定义所在
   * 的应用。
   *
   * 实现：调用 {@see App::appIdFromNamespace(static::class)}；DiscuzX 环境下内核命名
   * 空间 `kernel` 自动补 `gstudio_` 前缀。
   *
   * @return string|null 应用 ID；无法识别时返回 null
   */
  public function appId()
  {
    return App::appIdFromNamespace(static::class);
  }
}
