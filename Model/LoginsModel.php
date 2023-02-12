<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\KernelModel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class LoginsModel extends KernelModel
{
  public $tableName = "logins";
  public function getByToken($token)
  {
    return $this->where([
      "token" => $token
    ])->getOne();
  }
  public function deleteByToken($token)
  {
    return $this->where([
      "token" => $token
    ])->delete();
  }
}
