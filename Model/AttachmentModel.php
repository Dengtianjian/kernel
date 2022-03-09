<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;

class AttachmentModel extends Model
{
  public $tableName = "attachments";
  public function deleteByFileId(string $fileId)
  {
    return $this->where("fileId", $fileId)->delete(true);
  }
}
