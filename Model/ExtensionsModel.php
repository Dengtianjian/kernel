<?php

namespace kernel\Model;

if (!defined("F_KERNEL")) {
  exit("Access Denied");
}

use kernel\Foundation\Database\PDO\Model as PDOModel;

class ExtensionsModel extends PDOModel
{
  public $tableName = "kernel_extensions";
  public function getByExtensionId($extensionId)
  {
    return $this->where("extension_id", $extensionId)->getOne();
  }
}
