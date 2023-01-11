<?php

namespace kernel\Service;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Database\Model;
use kernel\Foundation\Service;

class UserService extends Service
{
  protected static $tableName = "common_member";

}
