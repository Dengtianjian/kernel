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

  /**
   * 上传对象
   *
   * @param string $ObjectKey 对象键
   * @param string $FilePath 本地文件名
   * @param array $Options 上传选项，具体有哪些选项可看https://cloud.tencent.com/document/product/436/64283
   * @return boolean 上传结果
   */
  public function upload($ObjectKey, $FilePath = "files", $Options = [])
  {
    try {
      $this->OSSSDKClient->upload(
        $this->OSSBucketName,
        $ObjectKey,
        fopen($FilePath, 'rb'),
        $Options
      );
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }

  function deleteFile($ObjectKey)
  {
    try {
      $this->OSSSDKClient->deleteObject([
        "Bucket" => $this->OSSBucketName,
        'Key' => $ObjectKey
      ]);
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }
  /**
   * 获取对象预览链接地址
   *
   * @param string $ObjectKey 对象名称
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @param integer $Expires 签名有效期
   * @param boolean $WithSignature 是否携带签名
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string HTTPS协议的对象访问链接地址
   */
  function getFilePreviewURL($ObjectKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true, $TempKeyPolicyStatement = [])
  {
    if ($TempKeyPolicyStatement) {
      $TempAuth = $this->OSSSTSClient->getTempKeysByPolicy($TempKeyPolicyStatement, $Expires);
    }

    return $this->OSSClient->getObjectAuthUrl($ObjectKey, "get", $URLParams, [], $Expires, false);
  }

  /**
   * 获取对象下载链接地址
   *
   * @param string $ObjectKey 对象名称
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @param integer $Expires 签名有效期
   * @param boolean $WithSignature 是否携带签名
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string HTTPS协议的对象访问链接地址
   */
  function getFileDownloadURL($ObjectKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true, $TempKeyPolicyStatement = [])
  {
    if ($TempKeyPolicyStatement) {
      $TempAuth = $this->OSSSTSClient->getTempKeysByPolicy($TempKeyPolicyStatement, $Expires);
    }

    return $this->OSSClient->getObjectAuthUrl($ObjectKey, "get", $URLParams, [], $Expires, true);
  }

  /**
   * 获取对象授权信息
   *
   * @param string $ObjectKey 对象名称
   * @param integer $Expires 有效期，秒级
   * @param string $HTTPMethod 调用的服务所使用的请求方法
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @return string 授权信息，k=v&v1=v1 字符串形式的结构
   */
  function getFileAuth(
    $ObjectKey,
    $Expires = 1800,
    $HTTPMethod = "get",
    $URLParams = [],
    $Headers = []
  ) {
    return $this->OSSClient->getAuth($ObjectKey, $Expires, $HTTPMethod, $URLParams, $Headers);
  }

  /**
   * 获取图片信息
   *
   * @param string $ObjectKey 对象键名
   * @return array|false
   */
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
  function getFileInfo($ObjectKey)
  {
    return NULL;
  }

  /**
   * 查看对象是否存在
   * @deprecated
   *
   * @param string $ObjectKey 对象名称
   * @return boolean
   */
  function doesObjectExist($ObjectKey)
  {
    try {
      return $this->OSSSDKClient->doesObjectExist(
        $this->OSSBucketName,
        $ObjectKey
      );
    } catch (\Exception $e) {
      return false;
    }
  }

  function fileExist($ObjectKey)
  {
    try {
      return $this->OSSSDKClient->doesObjectExist(
        $this->OSSBucketName,
        $ObjectKey
      );
    } catch (\Exception $e) {
      return false;
    }
  }
}
