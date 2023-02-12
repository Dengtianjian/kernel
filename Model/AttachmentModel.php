<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\SQL;

class AttachmentModel extends Model
{
  public $tableName = "attachments";
  public function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for attachments
-- ----------------------------
DROP TABLE IF EXISTS `{$this->tableName}`;
CREATE TABLE `{$this->tableName}`  (
  `id` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'id',
  `path` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '物理相对地址',
  `saveFileName` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '保存在服务器的文件名称',
  `fileId` varchar(160) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '文件Id',
  `userId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户Id',
  `organizationId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '组织Id',
  `fileName` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '源文件Id',
  `fileSize` double NULL DEFAULT NULL COMMENT '文件大小',
  `remote` tinyint(1) NULL DEFAULT NULL COMMENT '是否远程',
  `used` enum('0','1') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '1' COMMENT '附件是否被使用了',
  `remoteId` varchar(260) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '远程附件Id',
  `createdAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '创建时间',
  `updatedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '更新时间',
  `deletedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`, `fileId`, `userId`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;
SQL;
  }
  public function deleteByFileId(string $fileId)
  {
    return $this->where("fileId", $fileId)->delete(true);
  }
}
