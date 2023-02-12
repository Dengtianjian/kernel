<?php

namespace kernel\Platform\DiscuzX\Foundation\Database;

class DiscuzXTableModel extends DiscuzXModel
{
  function __construct($tableName = null)
  {
    if (!$tableName) $tableName = $this->tableName;
    parent::__construct($tableName);

    $this->query = new DiscuzXQuery($tableName);
  }
}
