<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\WechatUsersModel;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXDB;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXQuery;

class DiscuzXWechatUsersModel extends WechatUsersModel
{
  public $tableName = "gstudio_kernel_wechat_users";
  function __construct($tableName = null)
  {
    parent::__construct();

    $this->query = new DiscuzXQuery($this->tableName);

    $this->tableName = \DB::table($this->tableName);

    $this->DB = DiscuzXDB::class;
  }
  public function add($memberId, $openId, $unionId = null, $phone = null)
  {
    return $this->insert([
      "memberId" => $memberId,
      "openId" => $openId,
      "unionId" => $unionId,
      "phone" => $phone
    ]);
  }
  public function itemByOpenId($openId)
  {
    return $this->where("openId", $openId)->getOne();
  }
}
