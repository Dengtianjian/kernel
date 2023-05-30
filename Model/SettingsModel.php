<?php

namespace kernel\Model;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Database\PDO\Model;

class SettingsModel extends Model
{
  public $tableName = "settings";
  public static $CreatedAt = null;
  public static $DeletedAt = null;

  public function __construct($tableName = null)
  {
    if ($tableName) {
      $this->tableName = $tableName;
    }
    parent::__construct();
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for pre_gstudio_super_app_system_settings
-- ----------------------------
DROP TABLE IF EXISTS `{$this->tableName}`;
CREATE TABLE IF NOT EXISTS `{$this->tableName}`  (
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
    $setting = $this->where("name", $name)->getOne();
    if ($setting) {
      $setting['value'] = $setting['value'] ?: null;
      return unserialize($setting['value']) ?: $setting['value'];
    }
    return null;
  }
  /**
   * 获取多个设置项
   *
   * @param array $names 设置项名称数组
   * @return array
   */
  public function items(...$names)
  {
    $settingsData = $this->where("name", $names)->getAll();
    $settings = [];
    foreach ($settingsData as $setItem) {
      $setItem['value'] = $setItem['value'] ?: null;
      $settings[$setItem['name']] = unserialize($setItem['value']) ?: $setItem['value'];
    }
    return $settings;
  }
}
