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
}
