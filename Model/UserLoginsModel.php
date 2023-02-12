<?php

namespace kernel\Model;

use kernel\Foundation\Database\PDO\KernelModel;

class UserLoginsModel extends KernelModel
{
  public $tableName = "user_logins";
  public function getByToken($token)
  {
    return $this->where("deletedAt", null)->where("token", $token)->getOne();
  }
  public function deleteByToken(string $token)
  {
    return $this->where("token", $token)->delete(true);
  }
}
