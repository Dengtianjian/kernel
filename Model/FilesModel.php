<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\Schema;

/**
 * 文件模型
 *
 * @property int $id id
 * @property string $key 名称
 * @property string $disk 存储磁盘名称
 * @property string|null $ref 引用的ID
 * @property string|null $type 引用的业务
 * @property string|null $mime_type mime 类型
 * @property string|null $owner_id 所属 ID
 * @property string $source_file_name 原文件名称
 * @property string $name 保存后文件名称
 * @property float $size 文件尺寸
 * @property string|null $path 保存的文件路径
 * @property float|null $width 宽度（媒体文件才有该值）
 * @property float|null $height 高度（媒体文件才有该值）
 * @property string $extension 文件扩展名
 * @property string $access_control 访问控制权限
 * @property string $created_at 创建时间
 * @property string $updated_at 最后更新时间
 */
class FilesModel extends Model
{
  public $tableName = "files";

  /**
   * 字段类型映射（字段名 → PHP 类型）
   *
   * 与 $schema 列定义保持一致，用于：
   * - 构造时填充各字段默认值
   * - 读写时自动类型转换（__get / __set）
   *
   * @var array<string, string>
   */
  protected $casts = [
    "id" => "int",
    "key" => "string",
    "disk" => "string",
    "ref" => "string",
    "type" => "string",
    "mime_type" => "string",
    "owner_id" => "string",
    "source_file_name" => "string",
    "name" => "string",
    "size" => "float",
    "path" => "string",
    "width" => "float",
    "height" => "float",
    "extension" => "string",
    "access_control" => "string",
    "created_at" => "int",
    "updated_at" => "int",
  ];

  public function __construct()
  {
    $this->schema = [
      (new Schema("id"))->bigint()->unsigned()->nullable(false)->autoIncrement()->comment("id"),
      (new Schema("key"))->varchar(280)->nullable(false)->index("key")->unique()->comment("名称"),
      (new Schema("disk"))->varchar(32)->nullable(false)->default("local")->comment("存储磁盘名称"),
      (new Schema("ref"))->varchar(48)->comment("引用的ID"),
      (new Schema("type"))->varchar(128)->comment("引用的业务"),
      (new Schema("mime_type"))->varchar(64)->comment("mime类型"),
      (new Schema("owner_id"))->varchar(32)->comment("所属 ID"),
      (new Schema("source_file_name"))->varchar(250)->nullable(false)->comment("原文件名称"),
      (new Schema("name"))->varchar(250)->nullable(false)->comment("保存后文件名称"),
      (new Schema("size"))->double()->nullable(false)->comment("文件尺寸"),
      (new Schema("path"))->text()->comment("保存的文件路径"),
      (new Schema("width"))->double()->comment("宽度（媒体文件才有该值）"),
      (new Schema("height"))->double()->comment("高度（媒体文件才有该值）"),
      (new Schema("extension"))->varchar(30)->nullable(false)->comment("文件扩展名"),
      (new Schema("access_control"))->varchar(60)->default("private")->nullable(false)->comment("访问控制权限"),
      (new Schema("created_at"))->unixtime_ms()->nullable(false)->comment("创建时间"),
      (new Schema("updated_at"))->unixtime_ms()->nullable(false)->comment("最后更新时间"),
    ];

    parent::__construct();
  }
}
