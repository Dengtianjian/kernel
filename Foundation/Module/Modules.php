<?php

namespace kernel\Foundation\Module;

use kernel\Foundation\App;

/**
 * 模块管理器
 *
 * 负责装载（load）、卸载（unload）框架模块，并按名称检索、批量启停。
 * 内部以「模块名 => Module 实例」关联数组存储，模块名由 Module::name() 提供。
 */
class Modules
{
  /**
   * 已装载的模块列表（模块名 => 实例）
   *
   * @var array<string, Module>
   */
  protected array $modules = [];
  protected ?App $app = null;

  /**
   * 构建模块管理器
   *
   */
  public function __construct(App $app)
  {
    $this->app = $app;
  }

  /**
   * 登记模块（不自动启动）
   *
   * 若同名模块已存在，将被覆盖。
   *
   * @param Module $module 模块实例
   * @return static
   */
  public function register(Module $module): static
  {
    $this->modules[$module->name()] = $module;
    return $this;
  }

  /**
   * 装载模块
   *
   * 先登记，再按需启动（默认启动）。
   *
   * @param Module $module 模块实例
   * @param bool $boot 是否立即启动（默认 true）
   * @return static
   */
  public function load(Module $module, bool $boot = true): static
  {
    $this->register($module);
    if ($boot) {
      $module->boot();
    }
    return $this;
  }

  /**
   * 卸载模块
   *
   * 先停止（shutdown），再从管理器移除。
   *
   * @param string $name 模块名称
   * @return static
   * @throws \InvalidArgumentException 模块不存在时抛出
   */
  public function unload(string $name): static
  {
    if (!isset($this->modules[$name])) {
      throw new \InvalidArgumentException(sprintf('模块 [%s] 未装载，无法卸载', $name));
    }
    $this->modules[$name]->shutdown();
    unset($this->modules[$name]);
    return $this;
  }

  /**
   * 启动指定模块
   *
   * @param string $name 模块名称
   * @return static
   * @throws \InvalidArgumentException 模块不存在时抛出
   */
  public function boot(string $name): static
  {
    $module = $this->get($name);
    if ($module === null) {
      throw new \InvalidArgumentException(sprintf('模块 [%s] 未装载，无法启动', $name));
    }
    $module->boot();
    return $this;
  }

  /**
   * 停止指定模块（仍保留在管理器中，未移除）
   *
   * @param string $name 模块名称
   * @return static
   * @throws \InvalidArgumentException 模块不存在时抛出
   */
  public function shutdown(string $name): static
  {
    $module = $this->get($name);
    if ($module === null) {
      throw new \InvalidArgumentException(sprintf('模块 [%s] 未装载，无法停止', $name));
    }
    $module->shutdown();
    return $this;
  }

  /**
   * 批量启动所有已装载模块
   *
   * @return static
   */
  public function bootAll(): static
  {
    foreach ($this->modules as $module) {
      $module->boot();
    }
    return $this;
  }

  /**
   * 批量停止所有已装载模块（保留在管理器中，未移除）
   *
   * @return static
   */
  public function shutdownAll(): static
  {
    foreach ($this->modules as $module) {
      $module->shutdown();
    }
    return $this;
  }

  /**
   * 清空所有模块
   *
   * 先逐个停止，再移除全部。
   *
   * @return static
   */
  public function clear(): static
  {
    foreach ($this->modules as $module) {
      $module->shutdown();
    }
    $this->modules = [];
    return $this;
  }

  /**
   * 获取指定模块
   *
   * @param string $name 模块名称
   * @return Module|null 不存在时返回 null
   */
  public function get(string $name): ?Module
  {
    return $this->modules[$name] ?? null;
  }

  /**
   * 模块是否已装载
   *
   * @param string $name 模块名称
   * @return bool
   */
  public function has(string $name): bool
  {
    return isset($this->modules[$name]);
  }

  /**
   * 获取所有已装载模块
   *
   * @return array<string, Module>
   */
  public function all(): array
  {
    return $this->modules;
  }

  /**
   * 获取所有已装载模块的名称
   *
   * @return string[]
   */
  public function names(): array
  {
    return array_keys($this->modules);
  }

  /**
   * 已装载模块数量
   *
   * @return int
   */
  public function count(): int
  {
    return count($this->modules);
  }
}
