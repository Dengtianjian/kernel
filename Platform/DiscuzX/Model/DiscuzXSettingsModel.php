<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\SettingsModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXSettingsModel extends SettingsModel
{
  public $tableName = "gstudio_super_app_settings";
  public function __construct()
  {
    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;
  }
}
