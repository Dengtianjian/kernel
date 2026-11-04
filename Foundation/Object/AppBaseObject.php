<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\App;
use kernel\Foundation\Application\Applications;

/**
 * 应用感知基础对象（AppBaseObject）
 *
 * 为所有「业务可装配实体」（模型 / 模块 / 服务 / 存储 / 控制器等）提供统一的
 * 「应用归属」感知能力，解决子系统复用主系统类时的 app 归属问题。
 *
 * 背景：在 `new App(...)->setup(...)` 生命周期内实例化、注册的类，都属于「当前正在
 * 装配的应用」；即便在 download 进程里实例化了 super_app 的 setup 类，只要装配上下文
 * 正确，这些类拿到的 app 就是 super_app —— 等价于两个应用各自在跑。因此，判定一个
 * 实体「属于哪个应用」的依据应当是**类自身的命名空间**（静态、确定），而不是运行时
 * 的默认应用（可变、会随进程漂移）。
 *
 * 本类提供两个**同源**的归属查询，都基于「类定义所在插件」这一静态事实：
 * - {@see belongAppId()}：返回「类自身所属应用」的 ID（取 `static::class` 命名空间第一段）；
 * - {@see belongApp()}  ：返回「类自身所属应用」的实例（用 {@see belongAppId()} 反查）。
 *
 * 若要取「当前正在跑的应用」（运行时装配上下文），请直接用 {@see getApp()}（无参），
 * 无需经过本类——这是两个不同维度的语义，切勿混用。
 *
 * 继承关系：BaseObject → AppBaseObject → AbilityBaseObject →（Table / Model / Module / Service / ...）
 * 所有走 AbilityBaseObject 链的基类自动获得 belongApp() / belongAppId()；Controller 直接继承本类。
 */
class AppBaseObject extends BaseObject
{
  /**
   * 获取本实例所属应用的 ID（类归属）
   *
   * 归属由「类自身命名空间第一段」决定，而非运行时默认应用。保证子系统复用主系统
   * 类时（download 进程里 new 了 super_app 的模型 / 服务 / 模块），归属仍指向类定义
   * 所在的应用，不随进程漂移。
   *
   * 实现：调用 {@see App::appIdFromNamespace(static::class)}，其中 `static::class` 是
   * 后期静态绑定（实际被实例化的那个子类）。DiscuzX 环境下内核命名空间 `kernel` 会
   * 由 {@see \kernel\DiscuzX\Foundation\DiscuzXApp} 覆写自动补 `gstudio_` 前缀。
   *
   * @return string|null 应用 ID；无法识别时返回 null
   */
  public function belongAppId()
  {
    return App::appIdFromNamespace(static::class);
  }

  /**
   * 获取本实例所属应用的实例（类归属）
   *
   * 与 {@see belongAppId()} 同源：先取类归属 ID，再经 {@see Applications::get($id)}
   * 反查对应的 App 实例。
   *
   * 注意：这里**严格按归属查询，不做默认应用回落**。若该归属应用在当前进程尚未被
   * 装配（例如 download 进程里 super_app 未 setup），返回 null，而不会错误地落到当前
   * 进程的默认应用——后者会污染「归属」语义。取「当前运行应用」请改用 {@see getApp()}。
   *
   * @return App|null 所属应用实例；该归属应用未登记时返回 null
   */
  public function belongApp()
  {
    return Applications::get($this->belongAppId());
  }
}
