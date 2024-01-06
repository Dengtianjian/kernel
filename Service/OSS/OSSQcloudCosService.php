<?php

namespace kernel\Service\OSS;

use kernel\Foundation\Log;
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
      'scheme' => 'http',
      'credentials' => [
        'secretId' => $SecretId,
        'secretKey' => $SecretKey
      ]
    ]);

    parent::__construct("QCloudCos", $SecretId, $SecretKey, $Region, $Bucket);
  }

  function deleteFile($ObjectKey)
  {
    return $this->OSSSDKClient->deleteObject([
      "Bucket" => $this->OSSBucketName,
      'Key' => $ObjectKey
    ]);
  }
  function getFilePreviewURL($ObjectKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true, $TempKeyPolicyStatement = [])
  {
    if ($TempKeyPolicyStatement) {
      $TempAuth = $this->OSSSTSClient->getTempKeysByPolicy($TempKeyPolicyStatement, $Expires);
    }

    return $this->OSSClient->getObjectAuthUrl($ObjectKey, "get", $URLParams, [], $Expires, false);
  }
  function getFileDownloadURL($ObjectKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true, $TempKeyPolicyStatement = [])
  {
    if ($TempKeyPolicyStatement) {
      $TempAuth = $this->OSSSTSClient->getTempKeysByPolicy($TempKeyPolicyStatement, $Expires);
    }

    return $this->OSSClient->getObjectAuthUrl($ObjectKey, "get", $URLParams, [], $Expires, true);
  }
  function getFileAuth(
    $ObjectKey,
    $Expires = 1800,
    $HTTPMethod = "get",
    $URLParams = [],
    $Headers = []
  ) {
    return $this->OSSClient->getAuth($ObjectKey, $Expires, $HTTPMethod, $URLParams, $Headers);
  }
  function getImageInfo($ObjectKey)
  {
    try {
      $Response = $this->OSSSDKClient->ImageInfo([
        "Bucket" => $this->OSSBucketName,
        'Key' => $ObjectKey
      ]);
      return json_decode($Response['Data'], true);
    } catch (SDKQcloudCos\Exception\ServiceResponseException $e) {
      if ($e->getCosErrorCode() === "InvalidImageFormat") {
        return null;
      }
      Log::record($e->getMessage());
      return false;
    }
  }
}
