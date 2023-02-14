<?php

namespace kernel\Service;

use kernel\Foundation\Database\PDO\DB;
use kernel\Foundation\Response;
use kernel\Foundation\Service;
use kernel\Foundation\Store;

class AuthService extends Service
{
  static function generateToken($userId, $tokenSalt = [], $expiration = 30)
  {
    array_push($tokenSalt, $userId);
    $hashString = time() . ":" . implode(":", $tokenSalt);
    $hashString = password_hash($hashString, PASSWORD_DEFAULT);
    $nowTime = time();
    $expiration = 86400 * $expiration;
    $expirationDate = $nowTime + $expiration;
    Store::setApp([
      "auth" => [
        "value" => $hashString,
        "token" => $hashString,
        "expirationDate" => $expirationDate,
        "expiration" => $expiration
      ]
    ]);
    return [
      "value" => $hashString,
      "token" => $hashString,
      "expirationDate" => $expirationDate,
      "expiration" => $expiration
    ];
  }
}
