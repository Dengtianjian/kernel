<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\AttachmentsModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXAttachmentsModel extends AttachmentsModel
{
  function __construct()
  {
    $tableName = F_APP_ID . "_attachments";

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
        $item['extension']
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
      "extension"
    ], $values);
  }
  /**
   * 批量更新附件的所属ID以及所属ID类型
   *
   * @param array $attachId 附件ID数组
   * @param int|string $belongsId 所属ID
   * @param int|string $belongsType 所属ID类型
   * @return int
   */
  function bactchUpdateBelongsIdType($attachId, $belongsId, $belongsType)
  {
    return $this->where("attachId", $attachId)->update([
      "belongsId" => $belongsId,
      "belongsType" => $belongsType,
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
