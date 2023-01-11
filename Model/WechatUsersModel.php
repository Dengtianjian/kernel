<?php

namespace gstudio_kernel\Model;

use gstudio_kernel\Foundation\Database\Model;
use gstudio_kernel\Foundation\Output;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class WechatUsersModel extends Model
{
  public $tableName = "gstudio_kernel_wechat_users";
  public function bound($memberId, $openId)
  {
    return $this->where([
      "memberId" => $memberId,
      "openId" => $openId
    ])->exist();
  }
  public function bind($memberId, $openId, $unionId = null, $phone = null)
  {
    $now = time();
    return $this->insert([
      "memberId" => $memberId,
      "openId" => $openId,
      "unionId" => $unionId ?: "",
      "phone" => $phone ?: "",
      "createdAt" => $now,
      "updatedAt" => $now,
    ]);
  }
  public function register($openId, $unionId = null, $phone = null)
  {
    $now = time();
    return $this->insert([
      "openId" => $openId,
      "unionId" => $unionId ?: "",
      "phone" => $phone ?: "",
      "createdAt" => $now,
      "updatedAt" => $now,
    ]);
  }
  public function removeByMemberId($memberId)
  {
    return $this->where("memberId", $memberId)->delete();
  }
  public function removeByOpenId($openId)
  {
    return $this->where("openId", $openId)->delete();
  }
  public function removeByUnionId($unionId)
  {
    return $this->where("unionId", $unionId)->delete();
  }
  public function removeByPhone($phone)
  {
    return $this->where("phone", $phone)->delete();
  }
  public function updatePhone($memberId, $phone)
  {
    return $this->where("memberId", $memberId)->update([
      "phone" => $phone
    ]);
  }
}
