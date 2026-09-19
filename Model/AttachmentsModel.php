<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

class AttachmentsModel extends Model
{
  public $tableName = "attachments";
  static $DeletedAt = false;

  public function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for attachments
-- ----------------------------
DROP TABLE IF EXISTS `{$this->tableName}`;
CREATE TABLE IF NOT EXISTS `{$this->tableName}`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '附件数字ID',
  `attachId` varchar(32) NOT NULL COMMENT '附件ID',
  `remote` tinyint(4) NOT NULL DEFAULT 0 COMMENT '远程附件（OSS）',
  `belongsId` varchar(34) NULL COMMENT '所属ID',
  `belongsType` varchar(32) NULL COMMENT '所属ID类型',
  `userId` bigint(20) NOT NULL DEFAULT 0 COMMENT '附件上传用户ID',
  `sourceFileName` varchar(255) NOT NULL COMMENT '原本的文件名称',
  `fileName` varchar(255) NOT NULL COMMENT '保存后的文件名称',
  `fileSize` double NOT NULL COMMENT '文件尺寸',
  `filePath` text NOT NULL COMMENT '保存的文件路径',
  `width` double NULL DEFAULT 0 COMMENT '宽度（媒体文件才有该值）',
  `height` double NULL DEFAULT 0 COMMENT '高度（媒体文件才有该值）',
  `key` varchar(32) NULL DEFAULT "" COMMENT '是否需要秘钥才可以访问，0=不需要，1=需要',
  `extension` varchar(30) NOT NULL COMMENT '文件扩展名',
  `createdAt` varchar(12) NOT NULL COMMENT '创建时间',
  `updatedAt` varchar(12) NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `userId`(`userId`) USING BTREE COMMENT '用户ID'
) COMMENT = '附件' 
SQL;
  }
  public function item($id = null, $attachId = null, $belongsId = null, $belongsType = null, $userId = null, $extension = null)
  {
    return $this->filterNullWhere([
      "id" => $id,
      "attachId" => $attachId,
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "userId" => $userId,
      "extension" => $extension,
    ])->getOne();
  }
  public function list($id = null, $attachId = null, $belongsId = null, $belongsType = null, $userId = null, $extension = null)
  {
    return $this->filterNullWhere([
      "id" => $id,
      "attachId" => $attachId,
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "userId" => $userId,
      "extension" => $extension,
    ])->getAll();
  }
  public function deleteItem($id = null, $attachId = null, $belongsId = null, $belongsType = null, $userId = null, $extension = null)
  {
    return $this->filterNullWhere([
      "id" => $id,
      "attachId" => $attachId,
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "userId" => $userId,
      "extension" => $extension,
    ])->limit(1)->delete(true);
  }
  public function add($attachId, $userId, $sourceFileName, $fileName, $fileSize, $filePath, $width, $height, $extension, $belongsId = null, $belongsType = null, $remote = false, $withKey = false)
  {
    return $this->insert(array_filter([
      "attachId" => $attachId,
      "remote" => $remote,
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "userId" => $userId,
      "sourceFileName" => $sourceFileName,
      "fileName" => $fileName,
      "fileSize" => $fileSize,
      "filePath" => $filePath,
      "width" => $width,
      "height" => $height,
      "extension" => $extension,
      "key" => $withKey,
    ], function ($item) {
      return !is_null($item);
    }));
  }
}
