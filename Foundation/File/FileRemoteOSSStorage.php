<?php

namespace kernel\Foundation\File;

use kernel\Foundation\Exception\Exception;
use kernel\Service\OSS\OSSQcloudCosService;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FileRemoteOSSStorage extends FileRemoteStorage
{
  protected $RemoteStorageInstance = null;

  const OSS_PLATFORMS = ["QCloudCOS", "AliYunOSS"];
  const OSS_QCLOUD = "QCloudCOS";
  const OSS_ALIYUN = "AliYunOSS";

  /**
   * 实例化对象存储服务类
   *
   * @param "QCloudCOS"|"AliYunOSS" $OSSPlatform
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   * @param string $SignatureKey 签名秘钥
   */
  public function __construct($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey)
  {
    switch ($OSSPlatform) {
      case self::OSS_QCLOUD:
        $this->RemoteStorageInstance = new OSSQcloudCosService($SecretId, $SecretKey, $Region, $Bucket);
        break;
      case self::OSS_ALIYUN:
        break;
      default:
        throw new Exception("该OSS平台不支持");
        break;
    }

    parent::__construct(null, $SignatureKey);
  }

  /**
   * 获取访问对象授权信息
   *
   * @param string $FileKey 对象名称
   * @param integer $Expires 有效期，秒级
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 访问请求方法
   * @return string 对象访问授权信息
   */
  public function generateAccessAuth($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = true)
  {
    return $this->RemoteStorageInstance->getObjectAuth($FileKey, $HTTPMethod, $Expires, $URLParams, $Headers);
  }
  /**
   * 生成访问授权信息
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param boolean $Download 下载参数
   * @param integer $Expires 授权有效期
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string 授权信息
   */
  public function generateAccessURL($FileKey, $URLParams = [], $Headers = [], $Download = false, $Expires = 600, $TempKeyPolicyStatement = [])
  {
    return $this->RemoteStorageInstance->getObjectURL($FileKey, $Expires, $URLParams, $Headers, $TempKeyPolicyStatement, $Download);
  }
  /**
   * 生成访问授权信息
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param boolean $Download 链接打开是下载文件
   * @param integer $Expires 授权有效期
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string 授权信息
   */
  public function generateDownloadURL($FileKey, $URLParams = [], $Headers = [], $Download = true, $Expires = 600, $TempKeyPolicyStatement = [])
  {
    return $this->RemoteStorageInstance->getObjectURL($FileKey, $Expires, $URLParams, $Headers, $TempKeyPolicyStatement, true);
  }
  /**
   * 删除文件
   *
   * @param string $FileKey — 文件名
   * @return int
   */
  public function deleteFile($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = $this->verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return 0;
    }

    $File = $this->filesModel->item($FileKey);
    if (!$File) {
      return 1;
    }

    if ($File['acl'] === self::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return 2;
      }
    } else {
      if ($File['acl'] !== self::PUBLIC_READ_WRITE && $File['acl'] !== self::AUTHENTICATED_READ_WRITE) {
        if ($File['ownerId'] !== $CurrentAuthId) {
          return 3;
        }
      }
    }

    $this->RemoteStorageInstance->deleteObject($FileKey);

    return  $this->filesModel->remove(true, $FileKey);
  }
  /**
   * 获取图片信息
   *
   * @param string $ObjectKey 对象键名
   * @return array{width:int,height:int,size:int}|false
   */
  function getImageInfo($ObjectKey)
  {
    return $this->RemoteStorageInstance->getImageInfo($ObjectKey);
  }
}
