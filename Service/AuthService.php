<?php

namespace gstudio_kernel\Service;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use DB;
use gstudio_kernel\Foundation\Response;
use gstudio_kernel\Foundation\Service;

class AuthService extends Service
{
  protected static $tableName = "gstudio_kernel_logins";

  /**
   * 生成TOKEN哈希值
   *
   * @param int $userId 会员ID
   * @param array $tokenSalt 盐值
   * @param integer $expiration 有效期（天）
   * @return array value=哈希值 expirationDate=过期时间 expiration=有效期
   */
  static function generateToken($userId, $tokenSalt = [], $expiration = 30)
  {
    array_push($tokenSalt, $userId);
    $hashString = time() . ":" . implode(":", $tokenSalt);
    $hashString = password_hash($hashString, PASSWORD_DEFAULT);
    $nowTime = time();
    $expiration = 86400 * $expiration;
    self::Model()->insert([
      "id" => self::Model()->genId(),
      "token" => $hashString,
      "expiration" => $expiration,
      "userId" => $userId,
      "createdAt" => $nowTime,
      "updatedAt" => $nowTime
    ]);
    $expirationDate = $nowTime + $expiration;
    Response::header("Authorization", $hashString . "/" . $expirationDate, true);
    return [
      "value" => $hashString,
      "expirationDate" => $expirationDate,
      "expiration" => $expiration
    ];
  }
}
