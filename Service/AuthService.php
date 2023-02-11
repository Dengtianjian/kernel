<?php

namespace kernel\Service;

use kernel\Foundation\Database\PDO\DB;
use kernel\Foundation\Response;
use kernel\Foundation\Service;
use kernel\Foundation\Store;

class AuthService extends Service
{
  protected static $tableName = "logins";
  public static function initTable(string $extraFieldsSql = "")
  {
    $SQL = <<<SQL
  -- ----------------------------
-- Table structure for user_logins
-- ----------------------------
DROP TABLE IF EXISTS `logins`;
CREATE TABLE `user_logins`  (
  `id` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'id',
  `token` varchar(260) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'token值',
  `expiration` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '有效期至',
  `userId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '所属用户',
  `createdAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '创建时间',
  `updatedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '最后更新时间',
  `deletedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '删除时间',
  $extraFieldsSql
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;
SQL;
    DB::query($SQL);
  }
  static function generateToken(string $userId, array $tokenSalt = [], int $expiration = 30, array $extraFields = []): array
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
      "updatedAt" => $nowTime,
      ...$extraFields
    ]);
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
