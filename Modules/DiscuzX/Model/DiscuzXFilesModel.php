<?php

namespace kernel\Modules\DiscuzX\Model;

use kernel\Foundation\App;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

/**
 * 文件模型（DiscuzX）
 *
 * 表：`{appId}_files`（Discuz 表前缀由 {@see \kernel\Modules\DiscuzX\Foundation\Database\DiscuzXQuery::realTableName()}
 * 自动补上，实际落到 `pre_{appId}_files`），保存上传文件的元信息。
 *
 * **字段与内核 {@see \kernel\Model\FilesModel} 完全一致（snake_case）**：
 * 早期本表用的是 camelCase（`platform`/`belongsId`/`ownerId`/`sourceFileName`/`accessControl`/`createdAt`…），
 * 已由 `Iuu/Upgrades/Upgrade_1_3_0.php` 迁移成现在的字段（`disk`/`ref`/`type`/`mime_type`/`owner_id`/
 * `source_file_name`/`access_control`/`created_at`/`updated_at`，并删掉内核没有的 `remote`）。
 *
 * 建表：下方 `$tableStructureSQL` 记录本表结构，与迁移后的结构（也就是内核 `Schema` 生成的结构）逐列一致，
 * 供全新安装时由安装/升级脚本用 Discuz 的 `runquery()` 执行；用的是 `CREATE TABLE IF NOT EXISTS`，
 * **不含 DROP**，已存在的表不会被清空。
 *
 * 注意：`DiscuzXModel::__construct()` 刻意不调用 kernel 父类构造（不套 kernel 表前缀、不索取 PDO 驱动），
 * 因此构造末尾补了主键默认值 —— kernel `Model::save()` 以「主键值 === 默认值」判定走 INSERT，
 * 缺省会让新建记录误判成 UPDATE。
 *
 * @property int $id id
 * @property string $key 名称（文件键）
 * @property string $disk 存储磁盘名称
 * @property string|null $ref 引用的ID
 * @property string|null $type 引用的业务
 * @property string|null $mime_type mime类型
 * @property string|null $owner_id 所属 ID
 * @property string $source_file_name 原文件名称
 * @property string $name 保存后文件名称
 * @property float $size 文件尺寸
 * @property string|null $path 保存的文件路径
 * @property float|null $width 宽度（媒体文件才有该值）
 * @property float|null $height 高度（媒体文件才有该值）
 * @property string $extension 文件扩展名
 * @property string $access_control 访问控制权限
 * @property int $created_at 创建时间（unix 秒）
 * @property int $updated_at 最后更新时间（unix 秒）
 *
 * @see \kernel\Model\FilesModel 内核同结构模型（PDO 版；字段与类型以此为准）
 */
class DiscuzXFilesModel extends DiscuzXModel
{
  /**
   * 表名（不含 `pre_` 前缀，构造时若不传则取 `{appId}_files`）
   *
   * @var string
   */
  public $tableName = "";

  /**
   * 字段类型映射（字段名 => PHP 类型）
   *
   * 与建表 SQL 一一对应：
   * - 供 AR 读写时自动类型转换（kernel `Model::__get()` / `__set()`）；
   * - 声明 `created_at` / `updated_at` 即保留自动时间戳（`DiscuzXModel` 构造时会探测，
   *   二者缺一即自动关闭）；类型用 `unixtime`：内核 `freshTimestamp()` 产出**毫秒**，
   *   而本表的 `created_at` / `updated_at` 是 `int unsigned` 的 **unix 秒**，
   *   `castToDb()` 会按 `parseTimestamp()` 把毫秒换成秒再写库。
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
    "created_at" => "unixtime",
    "updated_at" => "unixtime",
  ];

  /**
   * 建表 SQL（DiscuzX 侧由安装/升级脚本交给 Discuz 的 `runquery()` 执行；空表示无需建表）
   *
   * @var string
   */
  protected $tableStructureSQL = "";

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
CREATE TABLE IF NOT EXISTS `pre_{$tableName}`  (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
  `key` varchar(280) NOT NULL COMMENT '名称',
  `disk` varchar(32) NOT NULL DEFAULT 'local' COMMENT '存储磁盘名称',
  `ref` varchar(48) NULL COMMENT '引用的ID',
  `type` varchar(128) NULL COMMENT '引用的业务',
  `mime_type` varchar(64) NULL COMMENT 'mime类型',
  `owner_id` varchar(32) NULL COMMENT '所属 ID',
  `source_file_name` varchar(250) NOT NULL COMMENT '原文件名称',
  `name` varchar(250) NOT NULL COMMENT '保存后文件名称',
  `size` double NOT NULL COMMENT '文件尺寸',
  `path` text NULL COMMENT '保存的文件路径',
  `width` double NULL COMMENT '宽度（媒体文件才有该值）',
  `height` double NULL COMMENT '高度（媒体文件才有该值）',
  `extension` varchar(30) NOT NULL COMMENT '文件扩展名',
  `access_control` varchar(60) NOT NULL DEFAULT 'private' COMMENT '访问控制权限',
  `created_at` int(10) unsigned NOT NULL COMMENT '创建时间',
  `updated_at` int(10) unsigned NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '文件' ROW_FORMAT = Dynamic;
SQL;

    parent::__construct($tableName);

    //* kernel save() 以「主键值 === 默认值」判定 INSERT；DiscuzXModel 不走 kernel 父类构造，
    //* $data 中没有主键默认值（null !== 0），会让新建记录误走 UPDATE，这里补上默认值。
    $this->id = 0;
  }
}
