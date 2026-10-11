<?php

namespace kernel\Modules\DiscuzX\Model\Wechat;

use kernel\Foundation\Database\PDO\Schema;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXWechatUsersModel extends DiscuzXModel
{
  public function __construct()
  {
    $tableName = getApp()::id() . "_wechat_users";

    $this->tableName = \DB::table($tableName);

    $this->schema = [
      (new Schema("id"))->bigint(20)->nullable(false)->autoIncrement()->comment("ID")->primary(),
      (new Schema("user_id"))->bigint(20)->nullable(true)->comment("被绑定的会员ID")->index("member_id"),
      (new Schema("open_id"))->varchar(64)->nullable(false)->comment("openId")->index("open_id"),
      (new Schema("union_id"))->varchar(64)->nullable(true)->comment("unionId")->index("union_id"),
      (new Schema("phone"))->varchar(12)->nullable(true)->comment("手机号码")->index("phone"),
      (new Schema("created_at"))->varchar(12)->nullable(false)->comment("创建时间"),
      (new Schema("updated_at"))->varchar(12)->nullable(false)->comment("最后更新时间"),
      (new Schema("deleted_at"))->varchar(12)->nullable(true)->comment("软删除时间")->index("deleted_at"),
    ];

    parent::__construct();
  }
}
