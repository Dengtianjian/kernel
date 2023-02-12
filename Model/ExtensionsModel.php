<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

if (!defined("F_KERNEL")) {
  exit("Access Denied");
}

class ExtensionsModel extends Model
{
  public $tableName = "extensions";
  public function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for extensions
-- ----------------------------
DROP TABLE IF EXISTS `{$this->tableName}`;

CREATE TABLE `{$this->tableName}` (
  `id` int(12) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `install_time` int(13) NULL DEFAULT NULL COMMENT '安装时间',
  `upgrade_time` int(13) NULL DEFAULT NULL COMMENT '更新时间',
  `local_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '本地版本',
  `plugin_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '所属插件id。kernel的是系统扩展',
  `extension_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '扩展id',
  `enabled` tinyint(1) NULL DEFAULT NULL COMMENT '已开启',
  `installed` tinyint(4) NULL DEFAULT NULL COMMENT '已安装',
  `path` varchar(535) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '扩展根路径',
  `parent_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '父扩展ID',
  `created_time` int(13) NULL DEFAULT NULL COMMENT '记录创建时间',
  `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '扩展名称',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `extension_id`(`extension_id`) USING BTREE,
  INDEX `plugin_id`(`plugin_id`) USING BTREE,
  INDEX `parent_id`(`parent_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;
SQL;
  }
  public function getByExtensionId($extensionId)
  {
    return $this->where("extension_id", $extensionId)->getAll();
  }
}
