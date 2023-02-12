<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\LoginsModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXLoginsModel extends LoginsModel
{
  public $tableName = "gstudio_kernel_logins";
  function __construct()
  {
    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;
  }
}
