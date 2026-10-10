<?php

namespace kernel\Platform\Wechat\Models;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\Schema;
use Override;

/**
 * 微信账号
 *
 * 存放微信公众号/小程序的账号信息（AppId/AppSecret）及账号级 access_token 缓存。
 *
 * 说明：
 * - access_token 为账号级接口调用凭证，同一 appId 全局唯一，多进程/跨请求共用本表，
 *   读写与刷新由 {@see \kernel\Platform\Wechat\WechatApi::accessToken()} 负责；
 * - 用户级凭证（网页授权 access_token、refresh_token、session_key 等）不属于本表，
 *   应存放在 WechatUsersModel 中。
 */
class WechatAccountsModel extends Model
{
  public function __construct()
  {
    $this->schema = [
      (new Schema("id"))->bigint()->nullable(false)->autoIncrement()->comment("id"),
      (new Schema("app_id"))->varchar(32)->nullable(false)->comment("app id"),
      (new Schema("app_secret"))->varchar(64)->nullable(false)->comment("app secret"),
      (new Schema("access_token"))->varchar(256)->nullable(true)->comment("access token"),
      (new Schema("access_token_expires_at"))->unixtime()->nullable(true)->comment("access token 过期时间"),
      (new Schema("type"))->enum(["official_account", "mini_promgram"])->nullable(false)->default("official_account")->comment("账号类型"),
      (new Schema("created_at"))->unixtime()->nullable(false)->comment("创建时间"),
      (new Schema("updated_at"))->unixtime()->nullable(false)->comment("最后更新时间")
    ];

    parent::__construct("wechat_accounts");
  }
}
