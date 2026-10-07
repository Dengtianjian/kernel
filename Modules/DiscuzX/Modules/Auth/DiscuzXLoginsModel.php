<?php

namespace kernel\Modules\DiscuzX\Modules\Auth;

use kernel\Foundation\App;
use kernel\Foundation\Database\PDO\Schema;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXLoginsModel extends DiscuzXModel
{
  /** @var bool 启用软删除（delete() 写 deleted_at，查询默认过滤 deleted_at IS NULL） */
  protected $softDelete = true;

  /**
   * 字段类型映射（字段名 → PHP 类型）
   *
   * 与 $schema 列定义保持一致，用于读写时自动类型转换。
   *
   * @var array<string, string>
   */
  protected $casts = [
    "id" => "string",
    "token" => "string",
    "expires_at" => "int",
    "user_id" => "string",
    "app_id" => "string",
    "created_at" => "int",
    "updated_at" => "int",
    "deleted_at" => "int",
    "salt" => "string",
    "expire_days" => "int",
  ];
  function __construct()
  {
    $this->schema = [
      (new Schema("id"))->bigint()->nullable(false)->autoIncrement()->comment("id")->primary(),
      (new Schema("token"))->varchar(260)->nullable(true)->index('token_value')->unsigned()->comment("token值"),
      (new Schema("user_id"))->varchar(26)->nullable(true)->comment("所属用户"),
      (new Schema("app_id"))->varchar(26)->nullable(true)->comment("所属app"),
      (new Schema("salt"))->varchar(64)->nullable(true)->comment("token 盐值（用于校验/轮换）"),
      (new Schema("expires_at"))->unixtime()->nullable(true)->comment("有效期至"),
      (new Schema("expire_days"))->int()->nullable(true)->comment("有效期天数"),
      (new Schema("created_at"))->unixtime()->nullable(true)->comment("创建时间"),
      (new Schema("updated_at"))->unixtime()->nullable(true)->comment("最后更新时间"),
      (new Schema("deleted_at"))->unixtime()->nullable(true)->comment("删除时间"),
    ];

    parent::__construct();

    $this->tableName = \DB::table(App::id() . "_logins");
  }
}
