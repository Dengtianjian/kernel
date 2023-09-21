<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\AttachmentsModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXAttachmentsModel extends AttachmentsModel
{
  protected $appId = F_APP_ID;
  function __construct()
  {
    parent::__construct();
    $this->tableName = $tableName = "{$this->appId}_attachments";
    $this->tableStructureSQL = <<<SQL
-- ----------------------------
-- Table structure for attachments
-- ----------------------------
DROP TABLE IF EXISTS `pre_{$this->tableName}`;
CREATE TABLE IF NOT EXISTS `pre_{$this->tableName}`  (
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
  `extension` varchar(30) NOT NULL COMMENT '文件扩展名',
  `createdAt` varchar(12) NOT NULL COMMENT '创建时间',
  `updatedAt` varchar(12) NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `userId`(`userId`) USING BTREE COMMENT '用户ID'
) COMMENT = '附件' 
SQL;

    $this->query = new DiscuzXQuery($tableName);

    $this->tableName = \DB::table($tableName);

    $this->DB = DiscuzXDB::class;
  }
  public function list($id = null, $attachId = null, $remote = null, $belongsId = null, $belongsType = null, $userId = null, $extension = null)
  {
    return $this->filterNullWhere([
      "id" => $id,
      "attachId" => $attachId,
      "remote" => $remote,
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "userId" => $userId,
      "extension" => $extension,
    ])->getAll();
  }
  public function listBelongsSameIdType($belongsId = null, $belongsType = null)
  {
    return $this->filterNullWhere([
      "belongsId" => $belongsId,
      "belongsType" => $belongsType
    ])->getAll();
  }
  public function item($id = null, $attachId = null, $remote = null, $belongsId = null, $belongsType = null, $userId = null, $extension = null)
  {
    return $this->filterNullWhere([
      "id" => $id,
      "attachId" => $attachId,
      "remote" => $remote,
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "userId" => $userId,
      "extension" => $extension,
    ])->limit(1)->getOne();
  }
  public function batchAddBelongingSameIdType($list, $belongsId, $belongsType)
  {
    $values = [];
    foreach ($list as $item) {
      $attachId = md5($item['filePath'] . $item['fileName'] . ":" . $belongsId . $belongsType . ":" . $item['userId'] . ":" . uniqid("attachment"));
      array_push($values, [
        $attachId,
        $item['remote'] ?: 0,
        $belongsId,
        $belongsType,
        $item['userId'],
        $item['sourceFileName'],
        $item['fileName'],
        $item['fileSize'],
        $item['filePath'],
        $item['width'],
        $item['height'],
        $item['extension'],
        $item['key']
      ]);
    }


    return $this->batchInsert([
      "attachId",
      "remote",
      "belongsId",
      "belongsType",
      "userId",
      "sourceFileName",
      "fileName",
      "fileSize",
      "filePath",
      "width",
      "height",
      "extension",
      "key"
    ], $values);
  }
  /**
   * 批量更新附件的所属ID以及所属ID类型
   *
   * @param array $attachId 附件ID数组
   * @param int|string $belongsId 所属ID
   * @param int|string $belongsType 所属ID类型
   * @param boolean $withKey 是否需要秘钥才可访问
   * @return int
   */
  function bactchUpdateBelongsIdType($attachIds, $belongsId, $belongsType, $withKey = false)
  {
    return $this->where("attachId", $attachIds)->update([
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "key" => $withKey,
    ]);
  }
  /**
   * 更新附件的所属ID以及所属ID类型
   *
   * @param array $attachId 附件ID
   * @param int|string $belongsId 所属ID
   * @param int|string $belongsType 所属ID类型
   * @param boolean $withKey 是否需要秘钥才可访问
   * @return int
   */
  function updateBelongsIdType($attachId, $belongsId, $belongsType, $withKey = false)
  {
    return $this->where("attachId", $attachId)->update([
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
      "key" => $withKey,
    ]);
  }
  function deleteBelongsSameIdType($belongsId, $belongsType)
  {
    return $this->where([
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
    ])->delete(true);
  }
}
