<?php

namespace kernel\Modules\DiscuzX\Model;

use kernel\Foundation\App;
use kernel\Model\AttachmentKeysModel;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXAttachmentKeysModel extends AttachmentKeysModel
{
  static $UpdatedAt = false;
  static $DeletedAt = false;

  function __construct()
  {
    $tableName = App::id() . "_attachment_keys";

    $this->query = new DiscuzXQuery($tableName);

    $this->tableName = \DB::table($tableName);

    $this->DB = DiscuzXDB::class;
  }
}
