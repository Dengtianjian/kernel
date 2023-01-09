<?php

namespace kernel\Model;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Database\Model;

if (!defined("F_KERNEL")) {
  exit("Access Denied");
}

class ExtensionsModel extends Model
{
  public $tableName = "kernel_extensions";
  public function getByExtensionId($extensionId)
  {
    return $this->where("extension_id", $extensionId)->getAll();
  }
}
