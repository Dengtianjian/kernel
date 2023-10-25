<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class LoginsModel extends Model
{
  public $tableName = "logins";
  public $tableStructureSQL = "";
  public function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for logins
-- ----------------------------
CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
  `id` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'id',
  `token` varchar(260) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'token值',
  `expiration` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '有效期至',
  `userId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '所属用户',
  `appId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '所属插件ID，如果为空即为通用',
  `createdAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '创建时间',
  `updatedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '最后更新时间',
  `deletedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;
SQL;
  }
  /**
   * 添加登入记录
   *
   * @param string $token 凭证
   * @param int $expiration 过期时间
   * @param int $userId 所属用户ID，整数数值
   * @param string $appId 所属app
   * @return int 记录ID
   */
  public function add($token, $expiration, $userId, $appId = F_APP_ID)
  {
    return $this->insert([
      "id" => $this->genId(),
      "token" => $token,
      "expiration" => $expiration,
      "userId" => $userId,
      "appId" => $appId
    ]);
  }
  public function getByToken($token)
  {
    return $this->where([
      "token" => $token,
      "deletedAt" => null
    ])->getOne();
  }
  public function deleteByToken($token)
  {
    return $this->where([
      "token" => $token
    ])->delete();
  }
}
