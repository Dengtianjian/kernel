<?php

namespace kernel\Platform\DiscuzX\Foundation\Database;


use kernel\Foundation\Database\PDO\Model;

class DiscuzXAddonModel extends DiscuzXModel
{
  function __construct($tableName = null, $prefix = null)
  {
    if (!$prefix) $prefix = "gstudio";

    parent::__construct($tableName, $prefix);
  }
}
