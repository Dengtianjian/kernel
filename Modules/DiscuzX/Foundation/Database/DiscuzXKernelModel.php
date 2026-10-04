<?php

namespace kernel\Modules\DiscuzX\Foundation\Database;

class DiscuzXKernelModel extends DiscuzXModel
{
  function __construct($tableName = null)
  {
    parent::__construct($tableName, "gstudio_kernel");
  }
}
