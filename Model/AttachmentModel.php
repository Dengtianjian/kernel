<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\KernelModel;

class AttachmentModel extends KernelModel
{
  public $tableName = "attachments";
  public function deleteByFileId(string $fileId)
  {
    return $this->where("fileId", $fileId)->delete(true);
  }
}
