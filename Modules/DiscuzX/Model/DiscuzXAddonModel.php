<?php

namespace kernel\Modules\DiscuzX\Model;

use kernel\Foundation\Database\RDB\Model;

/**
 * DiscuzX 插件表模型基类
 *
 * 面向插件**自建自用**的数据表，只处理一件事：把子类声明的裸表名拼成 Discuz 插件表的
 * 命名形式 `{应用id}_{表名}`。
 *
 * 子类声明**不含应用前缀**的表名，构造时经 {@see Model::discuzTableName()} 拼接：
 * ```php
 * class AddonModel extends DiscuzXAddonModel
 * {
 *   public $tableName = "addons";   // → gstudio_devaddon_addons
 * }
 * ```
 * 应用 id 取自 {@see \kernel\Foundation\App::id()}（经 {@see getApp()}），因此本类要求
 * 当前应用已启动。
 *
 * 与 {@see DiscuzXSystemModel} 的分工：
 * - 本类：插件自己建的表，表名以应用 id 打头，**不**带 Discuz 的 `tablepre`；
 * - {@see DiscuzXSystemModel}：Discuz 自带的系统表，表名要带 `tablepre`、**不**打应用 id。
 *
 * ⚠️ 表名恒定按 `{应用id}_{表名}` 拼接，不做环境判定 —— 类名已表明本类用于 DiscuzX 插件表。
 * 需要「同一套模型同时在 DiscuzX 与非 DiscuzX 环境复用、且表名随环境变化」的场景，
 * 请直接继承 {@see Model} 并自行判定环境（内核模块的 Setting / Auth / Wechat / Files 属此类）。
 *
 * ⚠️ 子类若把带应用前缀的完整表名写进 `$tableName`，会得到重复前缀的结果，
 * 请始终只声明后半段。
 *
 * @see Model 通用关系型模型基类
 * @see DiscuzXSystemModel Discuz 系统表模型基类
 */
class DiscuzXAddonModel extends Model
{
  /**
   * 构造
   *
   * 表名解析顺序：入参 → 子类声明的 `$tableName` → {@see Model::getDefaultTableName()} 按类名推断。
   * 取到裸表名后经 {@see Model::discuzTableName()} 拼上应用 id 前缀，再交父类完成初始化。
   *
   * @param string|null $tableName 不含应用前缀的表名；为空时取子类声明的 `$tableName`
   */
  public function __construct($tableName = null)
  {
    if (is_null($tableName)) {
      $tableName = $this->tableName ?: static::getDefaultTableName();
    }

    parent::__construct($this->discuzTableName($tableName));
  }
}
