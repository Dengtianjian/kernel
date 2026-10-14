<?php

namespace kernel\Modules\DiscuzX\Model\System;

use kernel\Modules\DiscuzX\Model\DiscuzXSystemModel;

/**
 * Discuz 用户组表模型
 *
 * 对应 Discuz 系统表 `common_usergroup`（真实表名含站点 `tablepre` 前缀，由基类补全）。
 *
 * @see DiscuzXSystemModel 基类：补 Discuz 表前缀、关闭时间戳自动维护
 */
class CommonUserGroupModel extends DiscuzXSystemModel
{
  public $tableName = "common_usergroup";

  /** @var string 主键字段名 */
  protected $primaryKey = "groupid";
}
