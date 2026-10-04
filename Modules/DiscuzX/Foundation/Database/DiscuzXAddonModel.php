<?php

namespace kernel\Modules\DiscuzX\Foundation\Database;

class DiscuzXAddonModel extends DiscuzXModel
{
  function __construct($tableName = null, $prefix = null)
  {
    if (!$prefix) $prefix = "gstudio";

    parent::__construct($tableName, $prefix);
  }
}
