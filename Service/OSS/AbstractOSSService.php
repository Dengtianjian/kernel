<?php

namespace kernel\Service\OSS;

use kernel\Foundation\Exception\Exception;
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
    if (!in_array($OSSPlatoform, ObjectStorageService::OSS_PLATFORMS)) {
      throw new Exception("该OSS平台不支持");
    }
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
  abstract function deleteObject($ObjectKey);

  /**
   * 获取对象访问链接地址
   *
   * @param string $objectName 对象名称
   * @param integer $DurationSeconds 签名有效期
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头部
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @param boolean $Download 链接打开是下载文件
   * @return string HTTPS协议的对象访问链接地址
   */
  abstract function getObjectURL($objectName, $DurationSeconds = 600, $URLParams = [], $Headers = [], $TempKeyPolicyStatement = [], $Download = false);

  /**
   * 获取对象授权信息
   *
   * @param string $objectName 对象名称
   * @param string $HTTPMethod 调用的服务所使用的请求方法
   * @param integer $DurationSeconds 有效期，秒级
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头部
   * @return string 授权信息，k=v&v1=v1 字符串形式的结构
   */
  abstract function getObjectAuth(
    $objectName,
    $HTTPMethod = "get",
    $DurationSeconds = 600,
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
