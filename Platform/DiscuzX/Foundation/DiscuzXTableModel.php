<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\Database\PDO\Query;

class DiscuzXTableModel extends DiscuzXModel
{
  function __construct($tableName = null)
  {
    if (!$tableName) $tableName = $this->tableName;
    parent::__construct($tableName);

    $this->query = new Query($tableName);
  }
}
