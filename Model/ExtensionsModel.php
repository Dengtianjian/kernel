<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\KernelModel;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

if (!defined("F_KERNEL")) {
  exit("Access Denied");
}

class ExtensionsModel extends KernelModel
{
  public $tableName = "extensions";
  public function getByExtensionId($extensionId)
  {
    return $this->where("extension_id", $extensionId)->getAll();
  }
}
