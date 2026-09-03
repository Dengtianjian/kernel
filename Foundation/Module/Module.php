<?php

namespace kernel\Foundation\Module;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 模块基类
 *
 * 框架级模块契约。每个模块拥有唯一名称与生命周期：
 * - boot（启动）：装载后调用一次，用于注册服务、绑定钩子等
 * - shutdown（停止）：卸载前调用一次，用于释放资源
 *
 * 业务模块可继承本类并按需重写 onBoot() / onShutdown()；
 * 若未重写，则默认空实现（即仅作为被管理器收纳的容器）。
 */
class Module extends AbilityBaseObject
{
  /**
   * 模块名称（唯一标识，作为管理器中的键名）
   *
   * @var string
   */
  protected string $name;

  /**
   * 是否已启动
   *
   * @var bool
   */
  protected bool $booted = false;

  /**
   * 构建模块
   *
   * @param string|null $name 模块名称；留空则取短类名作为默认名称
   */
  public function __construct(?string $name = null)
  {
    if ($name !== null) {
      $this->name = $name;
    } elseif (!isset($this->name) || $this->name === "") {
      $this->name = $this->defaultName();
    }
  }

  /**
   * 默认模块名称（短类名）
   *
   * @return string
   */
  protected function defaultName(): string
  {
    $parts = explode("\\", static::class);
    return end($parts);
  }

  /**
   * 获取模块名称
   *
   * @return string
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * 是否已启动
   *
   * @return bool
   */
  public function booted(): bool
  {
    return $this->booted;
  }

  /**
   * 启动模块（仅执行一次）
   *
   * @return void
   */
  final public function boot(): void
  {
    if ($this->booted) {
      return;
    }
    $this->onBoot();
    $this->booted = true;
  }

  /**
   * 停止模块（仅执行一次）
   *
   * @return void
   */
  final public function shutdown(): void
  {
    if (!$this->booted) {
      return;
    }
    $this->onShutdown();
    $this->booted = false;
  }

  /**
   * 启动钩子（子类重写）
   *
   * @return void
   */
  protected function onBoot(): void {}

  /**
   * 停止钩子（子类重写）
   *
   * @return void
   */
  protected function onShutdown(): void {}
}
