<?php

namespace kernel\Modules\DiscuzX\Model;

use kernel\Model\ExtensionsModel;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXExtensionsModel extends ExtensionsModel
{
  public $tableName = "gstudio_kernel_extensions";
  function __construct($tableName = null)
  {
    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;
  }
}
