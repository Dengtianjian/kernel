<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\AccessTokenModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXAccessTokenModel extends AccessTokenModel
{
  public $tableName = "gstudio_kernel_access_token";
  function __construct()
  {
    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;
  }
}
