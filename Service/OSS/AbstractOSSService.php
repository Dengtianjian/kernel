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
   * 密钥 ID
   *
   * @var string
   */
  protected $SecretId = null;

  /**
   * 密钥
   *
   * @var string
   */
  protected $SecretKey = null;

  /**
   * 实例化OSS服务类
   *
   * @param string $OSSPlatoform 远程存储平台名称
   * @param string $SecretId 密钥 ID
   * @param string $SecretKey 密钥
   * @param string $Region 存储地区
   * @param string $Bucket 存储桶名称
   */
  public function __construct($OSSPlatoform, $SecretId, $SecretKey, $Region, $Bucket)
  {
    $this->OSSPlatoform = $OSSPlatoform;
    $this->OSSBucketName = $Bucket;
    $this->OSSRegion = $Region;
    $this->SecretId = $SecretId;
    $this->SecretKey = $SecretKey;
  }

  /**
   * 上传文件
   *
   * @param string $ObjectKey 对象名称
   */
  abstract function upload($ObjectKey);
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
   */
  abstract function getFilePreviewURL($ObjectKey);

  /**
   * 获取对象下载链接地址
   *
   * @param string $ObjectKey 对象名称
   */
  abstract function getFileDownloadURL($ObjectKey);

  /**
   * 获取对象授权信息
   *
   * @param string $ObjectKey 对象名称
   */
  abstract function getFileAuth(
    $ObjectKey
  );

  /**
   * 查看对象是否存在
   *
   * @param string $ObjectKey 对象名称
   */
  abstract function fileExist($ObjectKey);
}
