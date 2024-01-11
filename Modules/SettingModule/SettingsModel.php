<?php

namespace kernel\Modules\SettingModule;

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
    foreach ($settingsData as $item) {
      if ($item['value']) {
        $v = unserialize($item['value']);
        $settings[$item['name']] = (is_bool($v) && $v === false) && strpos($item['value'], "b:") === false ? $item['value'] : $v;
      } else {
        $settings[$item['name']] = null;
      }
    }
    return $settings;
  }
  /**
   * 查询某个设置项是否存在
   *
   * @param string $name 设置项名称
   * @return bool
   */
  public function existItem($name)
  {
    return $this->where("name", $name)->exist();
  }
  /**
   * 添加设置项
   *
   * @param string $name 设置项名称
   * @param mixed $value 设置项值
   * @param boolean $serialization 是否需要序列化后存储
   * @return bool
   */
  public function add($name, $value = null, $serialization = true)
  {
    return $this->insert([
      "name" => $name,
      "value" => $serialization ? serialize($value) : $value,
    ]);
  }
  /**
   * 保存单个设置项
   *
   * @param string $name 设置项名称
   * @param string $value 设置项值
   * @param boolean $serialization 是否需要序列化后存储
   * @return bool
   */
  public function save($name, $value, $serialization = true)
  {
    return $this->where("name", $name)->update([
      "value" => $serialization ? serialize($value) : $value,
      "updatedAt" => time()
    ]);
  }
  /**
   * 保存多个设置项值
   *
   * @param array $settings 设置项键值对，键是设置项名称，值是设置项值
   * @return bool
   */
  public function saveItems($settings)
  {
    foreach ($settings as $name => $value) {
      $this->where("name", $name)->update([
        "value" => serialize($value),
        "updatedAt" => time()
      ]);
    }
    return true;
  }
}
