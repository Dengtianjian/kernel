<?php

namespace kernel\Platform\DiscuzX\Foundation\Database;

use kernel\Foundation\Database\PDO\Query;

class DiscuzXQuery extends Query
{
  function __construct($tableName)
  {
    $this->tableName = \DB::table($tableName);
  }
  static function ins($tableName)
  {
    return new Query(\DB::table($tableName));
  }
}
