<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

class AttachmentKeysModel extends Model
{
  static $UpdatedAt = false;
  static $DeletedAt = false;

  public $tableName = "attachment_keys";

  public function add($attachId, $key, $userId = null, $download = true, $preview = true, $expirationTime = null)
  {
    return $this->insert([
      "attachId" => $attachId,
      "key" => $key,
      "userId" => $userId,
      "download" => $download,
      "preview" => $preview,
      "expirationTime" => $expirationTime,
    ]);
  }
  public function list($id = null, $attachId = null, $userId = null)
  {
    return $this->filterNullWhere([
      "id" => $id,
      "attachId" => $attachId,
      "userId" => $userId
    ])->getAll();
  }
  public function item($id = null, $key = null, $attachId = null, $userId = null)
  {
    return $this->filterNullWhere([
      "id" => $id,
      "attachId" => $attachId,
      "key" => $key,
      "userId" => $userId
    ])->limit(1)->getOne();
  }
  public function deleteExpired()
  {
    return $this->where("expirationTime", time(), "<")->delete(true);
  }
}
