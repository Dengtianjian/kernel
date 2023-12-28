<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

class FilesModel extends Model
{
  public $tableName = "files";
  public function __construct()
  {
    $this->tableStructureSQL = <<<SQL
DROP TABLE IF EXISTS `files`;
CREATE TABLE `files`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '附件数字ID',
  `key` text NOT NULL COMMENT '文件名',
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
  `acl` varchar(64) NOT NULL DEFAULT 'private' COMMENT '访问权限控制',
  `extension` varchar(30) NOT NULL COMMENT '文件扩展名',
  `createdAt` varchar(12) NOT NULL COMMENT '创建时间',
  `updatedAt` varchar(12) NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `userId`(`authId`) USING BTREE COMMENT '用户ID'
) COMMENT = '文件';
SQL;
  }
  function add($FileKey, $SourceFileName, $SaveFileName, $FilePath, $FileSize, $Extension, $OwnerId = null, $ACL = 'private', $Remote = false, $BelongsId = null, $BelongsType = null, $Width = 0, $Height = 0)
  {
    return $this->insert([
      "key" => $FileKey,
      "sourceFileName" => $SourceFileName,
      "fileName" => $SaveFileName,
      "filePath" => $FilePath,
      "fileSize" => $FileSize,
      "extension" => $Extension,
      "remote" => $Remote,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
      "ownerId" => $OwnerId,
      "width" => $Width,
      "height" => $Height,
      "acl" => $ACL
    ]);
  }
  function save($Data, $FileKey = null, $Id = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey
    ])->update($Data);
  }
  function updateBelongs($BelongsId = null, $BelongsType = null, $FileKey = null, $Id = null)
  {
    return $this->save([
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ], $FileKey, $Id);
  }
  function item($FileKey = null, $BelongsId = null, $BelongsType, $OwnerId = null, $Id = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ])->getOne();
  }
  private $ListTotal = 0;
  function listTotal()
  {
    return $this->ListTotal;
  }
  function list($Page = 1, $PerPage = 10, $FileKey = null, $BelongsId = null, $BelongsType, $OwnerId = null, $Id = null)
  {
    $this->ListTotal = $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ])->reset(false)->count();

    $this->page($Page, $PerPage);

    return $this->getAll();
  }
  function remove($directly = false, $FileKey = null, $BelongsId = null, $BelongsType, $OwnerId = null, $Id = null)
  {
    return $this->filterNullWhere([
      "id" => $Id,
      "key" => $FileKey,
      "ownerId" => $OwnerId,
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ])->delete($directly);
  }
}
