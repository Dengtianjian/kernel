<?php

namespace kernel\Service\OSS;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Service;
use kernel\Platform\QCloud\QCloudCos\QCloudCosBase;
use kernel\Platform\QCloud\QCloudSTS;
use Qcloud\Cos as SDKQcloudCos;

class OSSService extends Service
{
  const OSS_PLATFORMS = ["QCloudCos", "AliYunOSS"];
  const OSS_QCLOUD = "QCloudCos";
  const OSS_ALIYUN = "AliYunOSS";

  /**
   * OSS服务实例
   *
   * @var AbstractOSSService
   */
  protected $OSS = null;
  /**
   * 实例化OSS服务类
   *
   * @param "QCloudCos"|"AliYunOSS" $OSSPlatoform
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   */
  public function __construct($OSSPlatoform, $SecretId, $SecretKey, $Region, $Bucket)
  {
    if (!in_array($OSSPlatoform, self::OSS_PLATFORMS)) {
      throw new Exception("该OSS平台不支持");
    }

    switch ($OSSPlatoform) {
      case self::OSS_QCLOUD:
        $this->OSS = new OSSQcloudCosService($SecretId, $SecretKey, $Region, $Bucket);
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
    return $this->OSS->deleteObject($ObjectKey);
  }
  /**
   * 获取对象访问URL链接地址
   *
   * @param string $objectName 对象名称
   * @param integer $expires 有效期，秒级
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @return string 对象访问URL链接地址
   */
  function getObjectURL($objectName, $expires = 600, $URLParams = [], $Headers = [])
  {
    return $this->OSS->getObjectURL($objectName, $expires, $URLParams, $Headers);
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
    return $this->OSS->getObjectAuth($objectName, $HTTPMethod, $expires, $URLParams, $Headers);
  }
}
