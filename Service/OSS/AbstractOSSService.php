<?php

namespace kernel\Service\OSS;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileRemoteOSSStorage;
use kernel\Foundation\Service;

/**
 * 抽象OSS服务类
 */
abstract class AbstractOSSService extends Service
{
  /**
   * 使用的OSS平台
   *
   * @var string
   */
  protected $OSSPlatoform = null;
  /**
   * kernel提供的OSS类实例
   *
   * @var object
   */
  protected $OSSClient = null;
  /**
   * OSS安全凭证服务实例
   *
   * @var object
   */
  protected $OSSSTSClient = null;
  /**
   * OSS平台提供的SDK客户端实例
   *
   * @var object
   */
  protected $OSSSDKClient = null;

  /**
   * 使用的存储桶名称
   *
   * @var string
   */
  protected $OSSBucketName = null;
  /**
   * 存储桶所在地域
   *
   * @var string
   */
  protected $OSSRegion = null;

  /**
   * 实例化OSS服务类
   *
   * @param string $OSSPlatoform
   * @param string $SecretId 
   * @param string $SecretKey
   * @param string $Region
   * @param string $Bucket
   */
  public function __construct($OSSPlatoform, $SecretId, $SecretKey, $Region, $Bucket)
  {
    $this->OSSPlatoform = $OSSPlatoform;
    $this->OSSBucketName = $Bucket;
    $this->OSSRegion = $Region;
  }

  /**
   * 删除对象
   *
   * @param string $ObjectKey 对象名称
   * @return mixed
   */
  abstract function deleteFile($ObjectKey);

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
  abstract function getFilePreviewURL($ObjectKey, $URLParams = [], $Headers = [], $Expires = 1800, $WithSignature = true,  $TempKeyPolicyStatement = []);

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
  abstract function getFileDownloadURL($ObjectKey, $URLParams = [], $Headers = [], $Expires = 1800, $WithSignature = true, $TempKeyPolicyStatement = []);

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
  abstract function getFileAuth(
    $ObjectKey,
    $Expires = 1800,
    $HTTPMethod = "get",
    $URLParams = [],
    $Headers = []
  );
  /**
   * 获取图片信息
   *
   * @param string $ObjectKey 对象键名
   * @return array|false
   */
  abstract function getImageInfo($ObjectKey);
}
