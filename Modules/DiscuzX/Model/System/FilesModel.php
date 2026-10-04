<?php

namespace kernel\Modules\DiscuzX\Model\System;

use kernel\Foundation\App;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

/**
 * 文件模型（DiscuzX）
 *
 * 存储聚合门面 {@see \kernel\Modules\DiscuzX\Foundation\Storage\DiscuzXStorage} **固定使用**的文件数据模型：
 * 保存上传文件的元信息（文件键、磁盘、尺寸、访问控制等）。
 *
 * 表名：`{appId}_files` —— 只需给 DiscuzXQuery 传不带前缀的表名，Discuz 表前缀由
 * {@see \kernel\Modules\DiscuzX\Foundation\Database\DiscuzXQuery::realTableName()} 自动补上，
 * 实际落到 `pre_{appId}_files`（与 `{@see DiscuzXAttachmentsModel}` 等 DZX 侧模型的命名方式一致）。
 *
 * 字段与内核 {@see \kernel\Model\FilesModel} 保持同名同类型（**snake_case**），
 * 因此 `FileStorage::save() / add() / update() / exists() / delete()` 等落库逻辑无需改动即可工作；
 * 差别只在底层执行：本模型走 Discuz 的 `\DB`（见 DiscuzXModel），不依赖内核 PDO 驱动。
 *
 * 建表：`createTable()`（继承自 DiscuzXModel）执行下方 `$tableStructureSQL`；安装/升级时调用一次即可。
 * 注意该 SQL 以 `DROP TABLE IF EXISTS` 开头（与 DZX 侧其它模型的安装约定一致）——**会清空已有文件记录**，
 * 若线上已有数据，请改用增量升级脚本，不要直接调 `createTable()`。
 *
 * @property int $id id
 * @property string $key 文件键（名称）
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
 * @property int $created_at 创建时间
 * @property int $updated_at 最后更新时间
 */
class FilesModel extends DiscuzXModel
{
  /**
   * 字段类型映射（与下方建表 SQL 一致）
   *
   * 用途：
   * - 构造时填充各字段默认值、读写时自动类型转换（kernel `Model::__get()` / `__set()`）；
   * - 声明 `created_at` / `updated_at` 即开启自动时间戳（DiscuzXModel 构造时会探测，缺列则自动关闭）。
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

  /**
   * 构造模型
   *
   * @param string|null $tableName 表名（不含 `pre_` 前缀）；不传时用 `{appId}_files`
   * @return void
   */
  public function __construct($tableName = null)
  {
    $tableName = $tableName ?: App::id() . "_files";

    $this->tableStructureSQL = <<<SQL
DROP TABLE IF EXISTS `pre_{$tableName}`;
CREATE TABLE IF NOT EXISTS `pre_{$tableName}`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id',
  `key` varchar(280) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '文件键（名称）',
  `disk` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'local' COMMENT '存储磁盘名称',
  `ref` varchar(48) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '引用的ID',
  `type` varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '引用的业务',
  `mime_type` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT 'mime类型',
  `owner_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '所属 ID',
  `source_file_name` varchar(250) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '原文件名称',
  `name` varchar(250) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '保存后文件名称',
  `size` double NOT NULL COMMENT '文件尺寸',
  `path` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '保存的文件路径',
  `width` double NULL DEFAULT 0 COMMENT '宽度（媒体文件才有该值）',
  `height` double NULL DEFAULT 0 COMMENT '高度（媒体文件才有该值）',
  `extension` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '文件扩展名',
  `access_control` varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'private' COMMENT '访问控制权限',
  `created_at` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '创建时间',
  `updated_at` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `key`(`key`) USING BTREE COMMENT '文件键'
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '文件' ROW_FORMAT = Dynamic;
SQL;

    parent::__construct($tableName);
  }
}
