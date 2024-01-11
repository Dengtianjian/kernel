<?php

namespace kernel\Platform\DiscuzX\Modules\SettingModule;

use kernel\Modules\SettingModule\SettingsModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXSettingsModel extends SettingsModel
{
  public $tableName = "";
  public function __construct($tableName = NULL)
  {
    if (is_null($tableName)) {
      $tableName = F_APP_ID . "_settings";
    }

    $this->tableName = $tableName;

    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;

    $this->tableStructureSQL = <<<SQL
    -- ----------------------------
    -- Table structure for pre_{F_APP_ID}_settings
    -- ----------------------------
    DROP TABLE IF EXISTS `pre_{$tableName}`;
    CREATE TABLE IF NOT EXISTS `pre_{$tableName}`  (
      `name` varchar(66) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '设置项名称',
      `value` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '设置项值',
      `updatedAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '设置项最后更新时间',
      PRIMARY KEY (`name`) USING BTREE
    ) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '系统设置' ROW_FORMAT = Dynamic;
SQL;
  }
  function createTable()
  {
    if (empty($this->tableStructureSQL)) return true;
    if (!function_exists("runquery")) {
      include_once libfile("function/plugin");
    }
    return runquery($this->tableStructureSQL);
  }
}
