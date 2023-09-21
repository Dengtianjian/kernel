<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

class AccessTokenModel extends Model
{
  public $tableName = "access_token";
  public $tableStructureSQL = "";
  public function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for access_token
-- ----------------------------
CREATE TABLE IF NOT EXISTS `{$this->tableName}`  (
  `accessToken` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'access_token',
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `platform` enum('wechatOfficialAccount','dingtalk') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '所属第三方平台',
  `createdAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '创建时间',
  `expiredAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '过期时间',
  `expires` varchar(6) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '有效期',
  `appId` varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '第三方平台的appid',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '第三方平台的AccessToken' ROW_FORMAT = Dynamic;
SQL;
  }
  public static $Timestamps = false;
  public function getPlatformLast($platform)
  {
    return $this->where("platform", $platform)->order("createdAt", "DESC")->getOne();
  }
  public function getPlatformLatest($platform)
  {
    return $this->where("platform", $platform)->where("expiredAt", time(), ">")->getOne();
  }
  public function deleteExpired($platform = null)
  {
    $query = $this->where("expiredAt", time(), ">");
    if ($platform) {
      $query->where("platform", $platform);
    }
    return $query->delete(true);
  }
  public function add($accessToken, $platform, $expiresIn, $appId = null)
  {
    $expiredAt = time() + $expiresIn - 300; //* 提前5分钟过期
    return $this->insert([
      "accessToken" => $accessToken,
      "platform" => $platform,
      "expires" => $expiresIn,
      "expiredAt" => $expiredAt,
      "appId" => $appId,
      "createdAt" => time()
    ]);
  }
}
