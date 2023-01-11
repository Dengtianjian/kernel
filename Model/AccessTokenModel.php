<?php

namespace gstudio_kernel\Model;

use gstudio_kernel\Foundation\Database\Model;

class AccessTokenModel extends Model
{
  public $tableName = "gstudio_kernel_access_token";
  public static $Timestamps = false;
  public function getPlatformLast($platform)
  {
    return $this->where("platform", $platform)->order("createdAt", "DESC")->getOne();
  }
  public function getPlatformLatest($platform)
  {
    return $this->where("platform", $platform)->where("expiredAt", time(), ">")->getOne();
  }
  public function deleteExpired($platform = null)
  {
    $query = $this->where("expiredAt", time(), ">");
    if ($platform) {
      $query->where("platform", $platform);
    }
    return $query->delete(true);
  }
  public function add($accessToken, $platform, $expiresIn, $appId = null)
  {
    $expiredAt = time() + $expiresIn - 300; //* 提前5分钟过期
    return $this->insert([
      "accessToken" => $accessToken,
      "platform" => $platform,
      "expires" => $expiresIn,
      "expiredAt" => $expiredAt,
      "appId" => $appId,
      "createdAt" => time()
    ]);
  }
}
