<?php

namespace kernel\Platform\QCloud\QCloudCos;

use kernel\Platform\QCloud\QCloud;

class QCloudCos extends QCloud
{
  /**
   * 存储桶所在地区，如 ap-guangzhou
   *
   * @var string
   */
  protected $Region = null;
  /**
   * 存储桶名称
   *
   * @var string
   */
  protected $Bucket = null;
  protected $SecurityToken = null;
  /**
   * 实例化腾讯云OSS存储类
   *
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   * @param string $host host开关
   * @param string $SecurityToken 安全令牌，如果使用临时秘钥，需要传入该参数
   */
  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $host = null, $SecurityToken = null)
  {
    if (is_null($host)) {
      $host = "{$Bucket}.cos.{$Region}.myqcloud.com";
    }
    $this->Region = $Region;
    $this->Bucket = $Bucket;
    $this->SecurityToken = $SecurityToken;

    parent::__construct($SecretId, $SecretKey, null, $host);
  }
  /**
   * @deprecated 
   */
  public function get($action, $version, $query = [])
  {
  }
  /**
   * @deprecated 
   */
  public function post($action, $version, $body = [], $query = [])
  {
  }
  /**
   * 获取授权参数
   *
   * @param string $objectName 路径名称，/开头
   * @param string $HTTPMethod 调用的服务所使用的请求方法
   * @param array $URLParams  请求的URL参数
   * @param array $Headers  请求头部
   * @param int $StartTime 授权开始时间，秒级时间戳
   * @param int $EndTime 授权结束时间，秒级时间戳
   * @return string 授权信息，k=v&v1=v1 字符串形式的结构
   */
  function getAuth($objectName, $HTTPMethod = "get", $URLParams = [], $Headers = [], $StartTime = null, $EndTime = null)
  {
    $QCCS = new QCloudCosSignture($this->SecretId, $this->SecretKey, $this->Region, $this->Bucket, $this->Host);

    if (!is_null($this->SecurityToken)) {
      $URLParams['x-cos-security-token'] = $this->SecurityToken;
    }

    if (strpos($objectName, "/") !== 0) {
      $objectName = "/" . $objectName;
    }

    return $QCCS->createAuthorization($objectName, $HTTPMethod, $URLParams, $Headers, $StartTime, $EndTime);
  }
  /**
   * 获取带有授权参数的对象访问URL
   *
   * @param string $objectName 路径名称，/开头
   * @param string $HTTPMethod 调用的服务所使用的请求方法
   * @param array $URLParams  请求的URL参数
   * @param array $Headers  请求头部
   * @param int $StartTime 授权开始时间，秒级时间戳
   * @param int $EndTime 授权结束时间，秒级时间戳
   * @return string https协议的对象访问URL
   */
  function getObjectAuthUrl($objectName, $HTTPMethod = "get", $URLParams = [], $Headers = [], $StartTime = null, $EndTime = null)
  {
    $Authorization = $this->getAuth($objectName, $HTTPMethod, $URLParams, $Headers, $StartTime, $EndTime);

    if (strpos($objectName, "/") !== 0) {
      $objectName = "/" . $objectName;
    }

    return "https://{$this->Host}{$objectName}?{$Authorization}";
  }
  /**
   * 通过路径和文件名称组合成对象名
   *
   * @param string $filePath 路径
   * @param string $fileName 文件名称
   * @return string
   */
  static function composeObjectName($filePath, $fileName)
  {
    return implode("/", [
      $filePath,
      $fileName
    ]);
  }
}
