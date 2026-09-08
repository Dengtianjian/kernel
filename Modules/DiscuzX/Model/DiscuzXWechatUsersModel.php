<?php

namespace kernel\Modules\DiscuzX\Model;

use kernel\Model\WechatUsersModel;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXWechatUsersModel extends WechatUsersModel
{
  public $tableName = "gstudio_kernel_wechat_users";
  function __construct($tableName = null)
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for wechat_users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `pre_{$this->tableName}` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `member_id` bigint(20) NULL DEFAULT NULL COMMENT '被绑定的会员ID',
  `open_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'openId',
  `union_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'unionId',
  `phone` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '手机号码',
  `created_at` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '创建时间',
  `updated_at` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '最后更新时间',
  `deleted_at` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`) USING BTREE COMMENT '会员ID索引',
  INDEX `union_id`(`union_id`) USING BTREE COMMENT 'UnionId索引',
  INDEX `open_id`(`open_id`) USING BTREE COMMENT 'OpenId索引',
  INDEX `phone`(`phone`) USING BTREE COMMENT '手机号索引'
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '微信用户表' ROW_FORMAT = Dynamic;
SQL;

    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;
  }
  public function add($memberId, $openId, $unionId = null, $phone = null)
  {
    $now = time();
    return $this->insert([
      "member_id" => $memberId,
      "open_id" => $openId,
      "union_id" => $unionId,
      "phone" => $phone,
      "created_at" => $now,
      "updated_at" => $now,
    ]);
  }
  public function itemByOpenId($openId)
  {
    return $this->where("open_id", $openId)->getOne();
  }
}
