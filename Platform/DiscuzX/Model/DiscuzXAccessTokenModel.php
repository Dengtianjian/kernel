<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\AccessTokenModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXAccessTokenModel extends AccessTokenModel
{
  public $tableName = "gstudio_kernel_access_token";
  function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for pre_access_token
-- ----------------------------
CREATE TABLE IF NOT EXISTS `pre_{$this->tableName}`  (
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

    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;
  }
}
