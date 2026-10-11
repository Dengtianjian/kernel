<?php

namespace kernel\Modules\DiscuzX\Model\Wechat;

use kernel\Foundation\Database\PDO\Schema;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXWechatAccountsModel extends DiscuzXModel
{
  public function __construct()
  {
    $tableName = getApp()::id() . "_wechat_accounts";

    $this->tableName = \DB::table($tableName);

    $this->schema = [
      (new Schema("id"))->bigint()->nullable(false)->autoIncrement()->comment("id"),
      (new Schema("app_id"))->varchar(32)->nullable(false)->comment("app id"),
      (new Schema("app_secret"))->varchar(64)->nullable(false)->comment("app secret"),
      (new Schema("access_token"))->varchar(256)->nullable(true)->comment("access token"),
      (new Schema("access_token_expires_at"))->unixtime()->nullable(true)->comment("access token 过期时间"),
      (new Schema("type"))->enum(["official_account", "mini_program"])->nullable(false)->default("official_account")->comment("账号类型"),
      (new Schema("created_at"))->unixtime()->nullable(false)->comment("创建时间"),
      (new Schema("updated_at"))->unixtime()->nullable(false)->comment("最后更新时间")
    ];

    parent::__construct();
  }
}
