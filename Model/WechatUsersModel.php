<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class WechatUsersModel extends Model
{
  public $tableName = "wechat_users";
  public function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for wechat_users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `memberId` bigint(20) NULL DEFAULT NULL COMMENT '被绑定的会员ID',
  `openId` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'openId',
  `unionId` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'unionId',
  `phone` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '手机号码',
  `createdAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '创建时间',
  `updatedAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '最后更新时间',
  `deletedAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `memberId`(`memberId`) USING BTREE COMMENT '会员ID索引',
  INDEX `unionId`(`unionId`) USING BTREE COMMENT 'UnionId索引',
  INDEX `openId`(`openId`) USING BTREE COMMENT 'OpenId索引',
  INDEX `phone`(`phone`) USING BTREE COMMENT '手机号索引'
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '微信用户表' ROW_FORMAT = Dynamic;
SQL;
  }
  public function bound($memberId, $openId)
  {
    return $this->where([
      "memberId" => $memberId,
      "openId" => $openId
    ])->exist();
  }
  public function bind($memberId, $openId, $unionId = null, $phone = null)
  {
    $now = time();
    return $this->insert([
      "memberId" => $memberId,
      "openId" => $openId,
      "unionId" => $unionId ?: "",
      "phone" => $phone ?: "",
      "createdAt" => $now,
      "updatedAt" => $now,
    ]);
  }
  public function register($openId, $unionId = null, $phone = null)
  {
    $now = time();
    return $this->insert([
      "openId" => $openId,
      "unionId" => $unionId ?: "",
      "phone" => $phone ?: "",
      "createdAt" => $now,
      "updatedAt" => $now,
    ]);
  }
  public function removeByMemberId($memberId)
  {
    return $this->where("memberId", $memberId)->delete();
  }
  public function removeByOpenId($openId)
  {
    return $this->where("openId", $openId)->delete();
  }
  public function removeByUnionId($unionId)
  {
    return $this->where("unionId", $unionId)->delete();
  }
  public function removeByPhone($phone)
  {
    return $this->where("phone", $phone)->delete();
  }
  public function updatePhone($memberId, $phone)
  {
    return $this->where("memberId", $memberId)->update([
      "phone" => $phone
    ]);
  }
}
