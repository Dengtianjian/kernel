<?php

namespace kernel\Service\OSS;

use kernel\Platform\QCloud\QCloudCos\QCloudCosBase;
use kernel\Platform\QCloud\QCloudSTS;
use Qcloud\Cos as SDKQcloudCos;

class OSSQcloudCosService extends AbstractOSSService
{
  /**
   * OSS类实例
   *
   * @var QCloudCosBase
   */
  protected $OSSClient = null;
  /**
   * OSS安全实例
   *
   * @var QCloudSTS
   */
  protected $OSSSTSClient = null;
  /**
   * 腾讯云OSS SDK客户端实例
   *
   * @var SDKQcloudCos\Client
   */
  protected $OSSSDKClient = null;

  /**
   * 实例化腾讯云COS服务
   *
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   */
  public function __construct($SecretId, $SecretKey, $Region, $Bucket)
  {
    $this->OSSClient = new QCloudCosBase($SecretId, $SecretKey, $Region, $Bucket);
    $this->OSSSTSClient = new QCloudSTS($SecretId, $SecretKey, $Region, $Bucket);
    $this->OSSSDKClient = new SDKQcloudCos\Client([
      'region' => $Region,
      'scheme' => 'https',
      'credentials' => [
        'secretId' => $SecretId,
        'secretKey' => $SecretKey
      ]
    ]);

    parent::__construct("QCloudCos", $SecretId, $SecretKey, $Region, $Bucket);
  }

  function deleteObject($ObjectKey)
  {
    return $this->OSSSDKClient->deleteObject([
      "Bucket" => $this->OSSBucketName,
      'Key' => $ObjectKey
    ]);
  }
  function getObjectURL($ObjectName, $DurationSeconds = 600, $URLParams = [], $Headers = [], $TempKeyPolicyStatement = [])
  {
    $startTime = time();
    $endTime = $startTime + $DurationSeconds;

    if ($TempKeyPolicyStatement) {
      $TempAuth = $this->OSSSTSClient->getTempKeysByPolicy($TempKeyPolicyStatement, $DurationSeconds);
    }

    return $this->OSSClient->getObjectAuthUrl($ObjectName, "get", $URLParams, $Headers, $startTime, $endTime);
  }
  function getObjectAuth(
    $ObjectName,
    $HTTPMethod = "get",
    $DurationSeconds = 600,
    $URLParams = [],
    $Headers = []
  ) {
    $StartTime = time();
    $EndTime = $StartTime + $DurationSeconds;

    return $this->OSSClient->getAuth($ObjectName, $HTTPMethod, $URLParams, $Headers, $StartTime, $EndTime);
  }
}
