<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;
use kernel\Traits\Model\FilesModelTrait;

class FilesModel extends Model
{
  use FilesModelTrait;

  public function __construct($tableName = "files")
  {
    parent::__construct($tableName);

    $this->tableStructureSQL = <<<SQL
DROP TABLE IF EXISTS `{$tableName}`;
CREATE TABLE `{$tableName}`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '附件数字ID',
  `key` varchar(280) NOT NULL COMMENT '文件名',
  `remote` tinyint(4) NOT NULL DEFAULT 0 COMMENT '远程附件',
  `belongsId` varchar(34) NULL DEFAULT NULL COMMENT '所属ID',
  `belongsType` varchar(32) NULL DEFAULT NULL COMMENT '所属ID类型',
  `ownerId` varchar(32) NULL DEFAULT NULL COMMENT '文件所有者ID',
  `sourceFileName` varchar(255) NOT NULL COMMENT '原本的文件名称',
  `name` varchar(255) NOT NULL COMMENT '保存后的文件名称',
  `size` double NOT NULL COMMENT '文件尺寸',
  `path` text NOT NULL COMMENT '保存的文件路径',
  `width` double NULL DEFAULT 0 COMMENT '宽度（媒体文件才有该值）',
  `height` double NULL DEFAULT 0 COMMENT '高度（媒体文件才有该值）',
  `extension` varchar(30) NOT NULL COMMENT '文件扩展名',
  `acl` varchar(64) NOT NULL DEFAULT 'private' COMMENT '访问权限控制',
  `createdAt` varchar(12) NOT NULL COMMENT '创建时间',
  `updatedAt` varchar(12) NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `key`(`key`) USING BTREE COMMENT '文件名'
) COMMENT = '文件';
SQL;
  }
}
