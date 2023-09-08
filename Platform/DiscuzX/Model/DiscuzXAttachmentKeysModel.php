<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\AttachmentKeysModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXAttachmentKeysModel extends AttachmentKeysModel
{
  static $UpdatedAt = false;
  static $DeletedAt = false;

  function __construct()
  {
    $tableName = F_APP_ID . "_attachment_keys";

    $this->query = new DiscuzXQuery($tableName);

    $this->tableName = \DB::table($tableName);

    $this->DB = DiscuzXDB::class;
  }
}
