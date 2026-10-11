<?php

namespace kernel\Modules\DiscuzX\Model;

use kernel\Foundation\Database\PDO\Schema;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXSettingsModel extends DiscuzXModel
{
  public function __construct($tableName = NULL)
  {
    $tableName = getApp()::id() . "_settings";

    $this->tableName = \DB::table($tableName);

    $this->schema = [
      (new Schema("name"))->varchar(66)->nullable(false)->comment("设置项名称")->primary(),
      (new Schema("value"))->text()->nullable(true)->comment("设置项值"),
      (new Schema("updated_at"))->varchar(12)->nullable(false)->comment("设置项最后更新时间"),
    ];
  }
}
