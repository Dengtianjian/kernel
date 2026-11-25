<?php

namespace kernel\Discuz\Models\System;

use kernel\Discuz\Models\DiscuzSystemModel;

/**
 * Discuz 站点设置表模型
 *
 * 对应 Discuz 系统表 `common_setting`（真实表名含站点 `tablepre` 前缀，由基类补全）。
 *
 * @see DiscuzSystemModel 基类：补 Discuz 表前缀、关闭时间戳自动维护
 */
class CommonSettingModel extends DiscuzSystemModel
{
  public $tableName = "common_setting";

  /** @var string 主键字段名 */
  protected $primaryKey = "skey";
}
