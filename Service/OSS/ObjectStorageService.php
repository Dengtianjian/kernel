<?php

namespace kernel\Service\OSS;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Service;

class ObjectStorageService extends Service
{
  const OSS_PLATFORMS = ["QCloudCos", "AliYunOSS"];
  const OSS_QCLOUD = "QCloudCos";
  const OSS_ALIYUN = "AliYunOSS";

  protected $ObjectStorageInstance = null;

  /**
   * 实例化对象存储服务类
   *
   * @param "QCloudCos"|"AliYunOSS" $OSSPlatform
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   */
  public function __construct($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket)
  {
    switch ($OSSPlatform) {
      case self::OSS_QCLOUD:
        $this->ObjectStorageInstance = new OSSQcloudCosService($SecretId, $SecretKey, $Region, $Bucket);
        break;
      case self::OSS_ALIYUN:
        break;
      default:
        throw new Exception("该OSS平台不支持");
        break;
    }
  }

  /**
   * 删除对象
   *
   * @param string $ObjectKey 对象名称
   * @return mixed
   */
  function deleteObject($ObjectKey)
  {
    return $this->ObjectStorageInstance->deleteObject($ObjectKey);
  }
  /**
   * 获取对象访问URL链接地址
   *
   * @param string $objectName 对象名称
   * @param integer $expires 有效期，秒级
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @param boolean $Download 链接打开是下载文件
   * @return string 对象访问URL链接地址
   */
  function getObjectURL($objectName, $expires = 600, $URLParams = [], $Headers = [], $TempKeyPolicyStatement = [], $Download = false)
  {
    return $this->ObjectStorageInstance->getObjectURL($objectName, $expires, $URLParams, $Headers, $TempKeyPolicyStatement, $Download);
  }
  /**
   * 获取访问对象授权信息
   *
   * @param string $objectName 对象名称
   * @param string $HTTPMethod 访问请求方法
   * @param integer $expires 有效期，秒级
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @return string 对象访问授权信息
   */
  function getObjectAuth(
    $objectName,
    $HTTPMethod = "get",
    $expires = 600,
    $URLParams = [],
    $Headers = []
  ) {
    return $this->ObjectStorageInstance->getObjectAuth($objectName, $HTTPMethod, $expires, $URLParams, $Headers);
  }
  /**
   * 获取图片信息
   *
   * @param string $ObjectKey 对象键名
   * @return array|false
   */
  function getImageInfo($ObjectKey)
  {
    return $this->ObjectStorageInstance->getImageInfo($ObjectKey);
  }
}
