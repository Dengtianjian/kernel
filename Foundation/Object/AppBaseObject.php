<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\App;
use kernel\Foundation\Application\Applications;

/**
 * 应用感知基础对象（AppBaseObject）
 *
 * 为「业务可装配实体」（模型 / 模块 / 服务 / 存储 / 控制器等）提供统一的
 * 「属于哪个应用」的判定能力。
 *
 * ## 归属语义
 *
 * 「这个对象属于哪个应用」按以下**两级顺序**解析（见 {@see belongAppId()}）：
 *
 *   ① 显式声明：由 `bindBelongApp()` 写入的归属；
 *   ② 命名空间兜底：`static::class` 的命名空间第一段（静态、确定）。
 *
 * 没有第三种来源。**不参考**「当前在哪个应用的 setup 里被创建」，也**不参考**「当前
 * 运行的是哪个应用」——这两者都是运行时状态，会让同一个类在不同调用路径下落到不同的
 * 表、签出不同的 URL，排查成本极高。归属必须能在读代码时确定。
 *
 * ## 三类语义，不要混用
 *
 * | 语义 | 入口 | 例子 |
 * |------|------|------|
 * | 对象归属（这个类/实例属于谁） | `belongAppId()` / `belongApp()` | `FilesModel` 显式绑 super_app |
 * | 当前运行应用（进程在跑谁） | {@see getApp()} / {@see App::id()} | download 进程 → download |
 * | 类所属应用（纯按命名空间） | {@see App::appIdFromNamespace()} | kernel 下的类 → kernel |
 *
 * ## 跨应用托管：用显式声明，不要靠推断
 *
 * 当本应用的实体需要操作「别的应用托管的数据/能力」时（例如子系统的文件统一走
 * super_app 文件中心），在创建处显式声明归属，而不是让它随装配过程漂移：
 *
 * ```php
 * // 存储归属 super_app：签名 ID、文件表都落在 super_app 下
 * $storage = new FileStorage($drivers, "gstudio_super_app");
 * ```
 *
 * 继承关系：BaseObject → AppBaseObject → AbilityBaseObject →（Table / Model / Module / Service / ...）
 * 所有走 AbilityBaseObject 链的基类自动获得 belongApp() / belongAppId() / bindBelongApp()；Controller 直接继承本类。
 */
class AppBaseObject extends BaseObject
{
  /**
   * 显式声明的应用 ID
   *
   * 由 {@see bindBelongApp()} 写入；为 null 表示未声明，归属回落到命名空间解析。
   *
   * @var string|null
   */
  private $appId = null;

  /**
   * 构造
   *
   * 本类**不做任何归属推断**：归属只来自显式声明或命名空间，与「谁在什么时机创建」
   * 无关。保留空实现是为了让子类能安全地链式调用 `parent::__construct()`
   * （{@see Table} / {@see Module} / {@see Controller} 均会调用）。
   */
  public function __construct()
  {
  }

  /**
   * 声明所属的 app（显式覆盖命名空间归属）
   *
   * 用于「类写在 A 应用，但操作的是 B 应用托管的数据/能力」的场景。优先级高于命名空间解析。
   *
   * @param string|App $appOrAppId appId 或者 App 实例
   * @return static
   */
  public function bindBelongApp($appOrAppId)
  {
    if (is_object($appOrAppId)) {
      $this->appId = $appOrAppId->id();
    } else {
      $this->appId = $appOrAppId;
    }

    return $this;
  }

  /**
   * 是否已显式声明归属
   *
   * 为 false 时 {@see belongAppId()} 会回落到命名空间解析。
   *
   * @return boolean
   */
  public function hasBelongApp()
  {
    return !is_null($this->appId);
  }

  /**
   * 获取本实例所属应用的 ID
   *
   * 解析顺序：
   * 1. 显式声明的归属（{@see bindBelongApp()}）；
   * 2. 类自身命名空间第一段（{@see App::appIdFromNamespace()}，后期静态绑定）。
   *
   * DiscuzX 环境下内核命名空间 `kernel` 会由
   * {@see \kernel\DiscuzX\Foundation\DiscuzXApp} 覆写自动补 `gstudio_` 前缀。
   *
   * @return string|null 应用 ID；无法识别时返回 null
   */
  public function belongAppId()
  {
    if ($this->appId) {
      return $this->appId;
    }

    //* 命名空间兜底：交给「当前默认应用」所属的 App 类解析，以便 DiscuzX 环境
    //* 自动补 `gstudio_` 前缀（App::appIdFromNamespace 是静态方法，直接调基类会丢掉覆写）
    $default = Applications::default();
    if ($default !== null) {
      $resolver = get_class($default);

      return $resolver::appIdFromNamespace(static::class);
    }

    return App::appIdFromNamespace(static::class);
  }

  /**
   * 获取本实例所属应用的实例
   *
   * 与 {@see belongAppId()} 同源：先取归属 ID，再经 {@see Applications::get($id)}
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
