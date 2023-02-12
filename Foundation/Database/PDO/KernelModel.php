<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Config;

class KernelModel extends Model
{
  public function __construct($tableName = null)
  {
    $this->prefixReplaces['{AppId}'] = F_KERNEL_ID;
    parent::__construct($tableName);
  }
}
