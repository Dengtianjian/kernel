<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Output;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class LoginsModel extends Model
{
  public $tableName = "kernel_logins";
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
