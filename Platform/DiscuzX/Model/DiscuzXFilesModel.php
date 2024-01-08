<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXModel;
use kernel\Traits\Model\FilesModelTrait;

class DiscuzXFilesModel extends DiscuzXModel
{
  use FilesModelTrait;


  public function __construct()
  {
    $tableName = F_APP_ID . "_files";

    parent::__construct($tableName);

    $this->tableStructureSQL = <<<SQL
DROP TABLE IF EXISTS `pre_{$tableName}`;
CREATE TABLE `pre_{$tableName}`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '附件数字ID',
  `key` varchar(280) NOT NULL COMMENT '文件名',
  `remote` tinyint(4) NOT NULL DEFAULT 0 COMMENT '远程附件',
  `belongsId` varchar(34) NULL DEFAULT NULL COMMENT '所属ID',
  `belongsType` varchar(32) NULL DEFAULT NULL COMMENT '所属ID类型',
  `ownerId` varchar(32) NULL DEFAULT NULL COMMENT '文件所有者ID',
  `sourceFileName` varchar(255) NOT NULL COMMENT '原本的文件名称',
  `fileName` varchar(255) NOT NULL COMMENT '保存后的文件名称',
  `fileSize` double NOT NULL COMMENT '文件尺寸',
  `filePath` text NOT NULL COMMENT '保存的文件路径',
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
  public function item($Key = null)
  {
    $item = $this->where("key", $Key)->getOne();
    if (!$item) return NULL;
    $item['remote'] = boolval(intval($item['remote']));

    return $item;
  }
  public function remove($Key = null)
  {
    return $this->filterNullWhere([
      "key" => $Key
    ])->delete(true);
  }
  public function existItem($Key = null)
  {
    return $this->filterNullWhere([
      "key" => $Key
    ])->exist();
  }
}
