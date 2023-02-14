<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

class SettingsModel extends Model
{
  public $tableName = "settings";
  public static $CreatedAt = null;
  public static $DeletedAt = null;

  public function __construct()
  {
    parent::__construct();
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for pre_gstudio_super_app_system_settings
-- ----------------------------
DROP TABLE IF EXISTS `{$this->tableName}`;
CREATE TABLE `{$this->tableName}`  (
  `name` varchar(66) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '设置项名称',
  `value` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '设置项值',
  `updatedAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '设置项最后更新时间',
  PRIMARY KEY (`name`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '系统设置' ROW_FORMAT = Dynamic;
SQL;
  }
  /**
   * 获取某个设置项
   *
   * @param string $name 设置项名称
   * @return null|array
   */
  public function item($name)
  {
    return $this->where("name", $name)->getOne();
  }
  /**
   * 获取多个设置项
   *
   * @param array $names 设置项名称数组
   * @return array
   */
  public function items(...$names)
  {
    return $this->where("name", $names)->getAll();
  }
}
