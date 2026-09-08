<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\Schema;

class AccessTokenModel extends Model
{
  public $tableName = "access_token";

  public function __construct()
  {
    $this->schema = [
      (new Schema("access_token"))->text()->nullable(false)->comment("access_token"),
      (new Schema("id"))->bigint(20)->nullable(false)->autoIncrement()->primary()->comment("ID"),
      (new Schema("platform"))->enum(['wechatOfficialAccount', 'dingtalk'])->nullable(false)->comment("所属第三方平台"),
      (new Schema("created_at"))->varchar(12)->nullable(false)->comment("创建时间"),
      (new Schema("expired_at"))->varchar(12)->nullable(false)->comment("过期时间"),
      (new Schema("expires"))->varchar(6)->nullable(false)->comment("有效期"),
      (new Schema("app_id"))->varchar(60)->nullable(false)->comment("第三方平台的appid"),
    ];

    parent::__construct();
  }

  /**
   * 获取某平台最近一次写入的 access_token（按创建时间倒序，不排除已过期）
   */
  public function getPlatformLast($platform)
  {
    return $this->where("platform", $platform)->orderBy("created_at", "DESC")->first();
  }

  /**
   * 获取某平台当前仍有效的 access_token（未过期）
   */
  public function getPlatformLatest($platform)
  {
    return $this->where("platform", $platform)->where("expired_at", time(), ">")->first();
  }

  /**
   * 物理删除已过期的 access_token
   */
  public function deleteExpired($platform = null)
  {
    $query = $this->where("expired_at", time(), "<");
    if ($platform) {
      $query->where("platform", $platform);
    }
    return $query->delete(true);
  }

  public function add($accessToken, $platform, $expiresIn, $appId = null)
  {
    $expiredAt = time() + $expiresIn - 300; //* 提前5分钟过期
    return $this->insert([
      "access_token" => $accessToken,
      "platform" => $platform,
      "expires" => $expiresIn,
      "expired_at" => $expiredAt,
      "app_id" => $appId,
      "created_at" => time()
    ]);
  }
}
