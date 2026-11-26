<?php

namespace kernel\Foundation\Builtin;

/**
 * 迁移接口
 *
 * 迁移目录下的每个 PHP 文件必须 return 一个实现本接口的对象。
 *
 * ```php
 * return new class implements Migration
 * {
 *   public function id() { return "202611260001_example"; }
 *   public function version() { return "1.3.0"; }
 *   public function up() {}
 *   public function down() {}
 * };
 * ```
 */
interface Migration
{
  /**
   * 稳定且可排序的迁移 ID
   *
   * 建议使用 `202611260001_xxx` 这类格式，执行顺序按 ID 升序。
   *
   * @return string
   */
  public function id();

  /**
   * 所属发布版本
   *
   * 用于按版本执行 / 回滚，以及首次启用时的基线判断。
   *
   * @return string
   */
  public function version();

  /**
   * 升级
   *
   * @return void
   */
  public function up();

  /**
   * 降级
   *
   * @return void
   */
  public function down();
}