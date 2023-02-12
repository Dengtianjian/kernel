<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\SQL;

class AttachmentModel extends Model
{
  public $tableName = "attachments";
  public function __construct()
  {
    parent::__construct($this->tableName);
    $this->tableStructureSQL = <<<SQL

SQL;
  }
  public function deleteByFileId(string $fileId)
  {
    return $this->where("fileId", $fileId)->delete(true);
  }
}
