<?php

namespace kernel\Modules\Setting;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\Schema;

/**
 * 系统设置模型（Setting）
 *
 * 纯粹的持久化模型：以键值对形式存储系统设置项，值经 serialize() 序列化后存入 `value` 字段。
 * 表结构以 Schema 声明（见 $schema），由 Model 基类在 Provisioner/安装阶段据此自动建表。
 *
 * 设置项的增删改查业务逻辑（含读取时的反序列化、序列化写入、`updated_at` 维护）
 * 已抽取到 `Setting` 模块类（kernel\Modules\Setting\Setting），本模型只负责数据存取，
 * 不承载业务方法。
 *
 * 查询统一走 Model 的查询构建器（scopedBuilder 代理）：first()/get()/exists()/
 * insert()/update()/where()，每次查询均为全新构建器，条件不会跨调用累积。
 *
 * @property string $name 设置项名称（主键）
 * @property string|null $value 设置项值（序列化后的字符串）
 * @property string $updated_at 最后更新时间（time() 字符串）
 */
class SettingsModel extends Model
{
  public $tableName = "settings";

  public function __construct()
  {
    $this->schema = [
      (new Schema("name"))->varchar(66)->nullable(false)->comment("设置项名称")->primary(),
      (new Schema("value"))->text()->nullable(true)->comment("设置项值"),
      (new Schema("updated_at"))->varchar(12)->nullable(false)->comment("设置项最后更新时间"),
    ];

    parent::__construct();
  }
}
