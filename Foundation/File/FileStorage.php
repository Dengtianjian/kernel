<?php

namespace kernel\Foundation\File;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\URL;
use kernel\Model\FilesModel;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FileStorage extends Files
{
  /**
   * 私有的，创作者与管理员具备全部权限，其他人没有权限
   */
  const PRIVATE = "private";
  /**
   * 共有读的，匿名用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const PUBLIC_READ = "public-read";
  /**
   * 公有读写，创建者、管理员和匿名用户具备全部权限，通常不建议授予此权限
   */
  const PUBLIC_READ_WRITE = "public-read-write";
  /**
   * 认证用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const AUTHENTICATED_READ = "authenticated-read";
  /**
   * 创建者、管理员和认证用户具备全部权限，通常不建议授予此权限
   */
  const AUTHENTICATED_READ_WRITE = "authenticated-read-write";

  /**
   * 生成访问授权信息
   *
   * @param string $FilePath 文件路径
   * @param string $FileName 文件名称
   * @param string $SignatureKey 签名秘钥
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param string $AuthId 授权ID。一般用于场景是，当前签名只允许给某个用户使用，就可传入该值；校验签名时也需要传入该值，并且校验请求参数的AuthId是否和传入的AuthId一致，不一致就是校验不通过。
   * @param string $HTTPMethod 请求方式
   * @param string $ACL 访问权限控制
   * @param boolean $toString 字符串形式返回参数，如果传入false，将会返回参数数组
   * @return string|array 授权信息
   */
  static function generateAccessAuth($FilePath, $FileName, $SignatureKey, $Expires = 600, $URLParams = [], $AuthId = null, $HTTPMethod = "get", $toString = false)
  {
    $FSS = new FileStorageSignature($SignatureKey);
    $FileKey = self::combinedFileKey($FilePath, $FileName);

    if ($AuthId) {
      $URLParams['authId'] = $AuthId;
    }

    return $FSS->createAuthorization($FileKey, $URLParams, [], $Expires, $HTTPMethod, $toString);
  }
  /**
   * 验证授权签名
   *
   * @param string $SignatureKey 签名秘钥
   * @param string $FileKey 文件名称
   * @param array $RawURLParams 请求参数
   * @param array $RawHeaders 请求头
   * @param string $AuthId 授权ID，用于校验请求参数中的AuthId是否与当前值一致
   * @param string $HTTPMethod 请求方式
   * @return boolean truly验证通过，返回false或者数字就是验证失败
   */
  static function verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders = [], $AuthId = null, $HTTPMethod = "get")
  {
    $URLParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    foreach ($URLParamKeys as $key) {
      if (!array_key_exists($key, $RawURLParams)) {
        return 0;
      }
    }
    $SignAlgorithm = $RawURLParams['sign-algorithm'];
    $SignTime = $RawURLParams['sign-time'];
    $KeyTime = $RawURLParams['key-time'];
    $HeaderList = $RawURLParams['header-list'] ? explode(";", urldecode($RawURLParams['header-list'])) : [];
    $URLParamList = $RawURLParams['url-param-list'] ? explode(";", rawurldecode(urldecode($RawURLParams['url-param-list']))) : [];
    $URLAuthId = rawurldecode(urldecode($RawURLParams['authId']));
    $Signature = $RawURLParams['signature'];

    if ((!is_null($AuthId) || array_key_exists("authId", $RawURLParams)) && $URLAuthId !== !$AuthId) return 1;

    if ($SignAlgorithm !== FileStorageSignature::getSignAlgorithm()) return 2;
    if (strpos($SignTime, ";") === false || strpos($KeyTime, ";") === false) return 3;
    if ($SignTime !== $KeyTime) return 4;
    list($startTime, $endTime) = explode(";", $SignTime);
    list($keyStartTime, $keyEndTime) = explode(";", $KeyTime);
    $startTime = intval($startTime);
    $endTime = intval($endTime);
    $keyStartTime = intval($keyStartTime);
    $keyEndTime = intval($keyEndTime);
    if ($endTime < $startTime) return 5;
    if ($endTime < time()) return 6;
    if ($keyEndTime < $keyStartTime) return 7;
    if ($keyEndTime < time()) return 8;

    $Headers = [];
    if ($HeaderList) {
      foreach ($RawHeaders as $key => $value) {
        $key = rawurldecode(urldecode($key));
        $value = rawurldecode(urldecode($value));
        if (!array_key_exists($key, $HeaderList)) {
          return 9;
        }
        $Headers[$key] = $value;
      }
    }

    $URLParams = [];
    foreach ($RawURLParams as $key => $value) {
      $key = rawurldecode(urldecode($key));
      $value = rawurldecode(urldecode($value));
      if (!in_array($key, $URLParamList)) {
        if (!in_array($key, $URLParamKeys)) {
          return 10;
        }
      }
      if (!in_array($key, $URLParamKeys)) {
        $URLParams[$key] = $value;
      }
    }

    return FileStorageSignature::call($SignatureKey)->verifyAuthorization($Signature, $FileKey, $startTime, $endTime, $URLParams, $Headers, $HTTPMethod);
  }
  /**
   * 生成访问链接
   *
   * @param string $FilePath 文件路径
   * @param string $FileName 文件名称
   * @param array $URLParams 请求参数
   * @param string $SignatureKey 签名秘钥
   * @param integer $Expires 有效期，秒级
   * @param string $AuthId 授权ID。一般用于场景是，当前签名只允许给某个用户使用，就可传入该值；校验签名时也需要传入该值，并且校验请求参数的AuthId是否和传入的AuthId一致，不一致就是校验不通过。
   * @param string $HTTPMethod 请求方式
   * @return string 访问URL
   */
  static function generateAccessURL($FilePath, $FileName, $URLParams = [], $SignatureKey = null, $Expires = 600, $AuthId = null, $HTTPMethod = "get")
  {
    $FileKey = rawurlencode(self::combinedFileKey($FilePath, $FileName));
    if ($SignatureKey) {
      $URLParams = array_merge($URLParams, self::generateAccessAuth($FilePath, $FileName, $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, false));
    }

    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "files/{$FileKey}";
    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
}
