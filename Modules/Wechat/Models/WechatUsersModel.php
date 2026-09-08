<?php

namespace kernel\Platform\Wechat\Models;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\Schema;

class WechatUsersModel extends Model
{
  public $tableName = "wechat_users";

  public function __construct()
  {
    $this->schema = [
      (new Schema("id"))->bigint(20)->nullable(false)->autoIncrement()->comment("ID")->primary(),
      (new Schema("member_id"))->bigint(20)->nullable(true)->comment("被绑定的会员ID")->index("member_id"),
      (new Schema("open_id"))->varchar(64)->nullable(false)->comment("openId")->index("open_id"),
      (new Schema("union_id"))->varchar(64)->nullable(true)->comment("unionId")->index("union_id"),
      (new Schema("phone"))->varchar(12)->nullable(true)->comment("手机号码")->index("phone"),
      (new Schema("created_at"))->varchar(12)->nullable(false)->comment("创建时间"),
      (new Schema("updated_at"))->varchar(12)->nullable(false)->comment("最后更新时间"),
      (new Schema("deleted_at"))->varchar(12)->nullable(true)->comment("软删除时间")->index("deleted_at"),
    ];

    parent::__construct();
  }
  public function bound($memberId, $openId)
  {
    return $this->where([
      "member_id" => $memberId,
      "open_id" => $openId
    ])->exist();
  }
  public function bind($memberId, $openId, $unionId = null, $phone = null)
  {
    $now = time();
    return $this->insert([
      "member_id" => $memberId,
      "open_id" => $openId,
      "union_id" => $unionId ?: "",
      "phone" => $phone ?: "",
      "created_at" => $now,
      "updated_at" => $now,
    ]);
  }
  public function register($openId, $unionId = null, $phone = null)
  {
    $now = time();
    return $this->insert([
      "open_id" => $openId,
      "union_id" => $unionId ?: "",
      "phone" => $phone ?: "",
      "created_at" => $now,
      "updated_at" => $now,
    ]);
  }
  public function removeByMemberId($memberId, $directly = false)
  {
    return $this->where("member_id", $memberId)->delete($directly);
  }
  public function removeByOpenId($openId)
  {
    return $this->where("open_id", $openId)->delete();
  }
  public function removeByUnionId($unionId)
  {
    return $this->where("union_id", $unionId)->delete();
  }
  public function removeByPhone($phone)
  {
    return $this->where("phone", $phone)->delete();
  }
  public function updatePhone($memberId, $phone)
  {
    return $this->where("member_id", $memberId)->update([
      "phone" => $phone
    ]);
  }
}
