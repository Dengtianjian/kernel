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
   */
  public function __construct($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket)
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

    parent::__construct(null, null);
  }

  /**
   * 删除文件
   *
   * @param string $FileKey — 文件名
   * @return mixed
   */
  public function deleteFile($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    return $this->RemoteStorageInstance->deleteObject($FileKey);
  }

  /**
   * 生成访问授权信息
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @param boolean $Download 链接打开是下载文件
   * @return string 授权信息
   */
  public function generateAccessURL($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $TempKeyPolicyStatement = [], $Download = FALSE)
  {
    return $this->RemoteStorageInstance->getObjectURL($FileKey, $Expires, $URLParams, $Headers, $TempKeyPolicyStatement, $Download);
  }
  /**
   * 获取访问对象授权信息
   *
   * @param string $FileKey 对象名称
   * @param integer $Expires 有效期，秒级
   * @param array $URLParams URL的query参数
   * @param string $HTTPMethod 访问请求方法
   * @param array $Headers 请求头
   * @return string 对象访问授权信息
   */
  public function generateAccessAuth($FileKey, $Expires = 600, $URLParams = [], $HTTPMethod = "get", $Headers = [])
  {
    return $this->RemoteStorageInstance->getObjectAuth($FileKey, $HTTPMethod, $Expires, $URLParams, $Headers);
  }
  /**
   * 获取图片信息
   *
   * @param string $ObjectKey 对象键名
   * @return array|false
   */
  function getImageInfo($ObjectKey)
  {
    return $this->RemoteStorageInstance->getImageInfo($ObjectKey);
  }
}
