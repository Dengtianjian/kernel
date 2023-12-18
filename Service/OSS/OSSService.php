<?php

namespace kernel\Service\OSS;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Service;

class OSSService extends Service
{
  const OSS_PLATFORMS = ["QCloudCos", "AliYunOSS"];
  const OSS_QCLOUD = "QCloudCos";
  const OSS_ALIYUN = "AliYunOSS";

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
  /**
   * 生成对象键名
   *
   * @param string $SourceFileName 原文件名
   * @param string $FilePath  保存的文件路径
   * @param string $extension 文件扩展名，如果该值为空，将会从原文件名中获取扩展名
   * @return string
   */
  static function generateObjectFileName($SourceFileName, $FilePath, $extension = null)
  {
    $objectName = md5(implode("/", [
      $FilePath,
      $SourceFileName,
      time()
    ]));

    if (is_null($extension)) {
      $extension = pathinfo($SourceFileName, PATHINFO_EXTENSION);
    }

    $objectKey = implode("/", [
      $FilePath,
      $objectName
    ]);

    return "{$objectKey}.{$extension}";
  }

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
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string 对象访问URL链接地址
   */
  function getObjectURL($objectName, $expires = 600, $URLParams = [], $Headers = [], $TempKeyPolicyStatement = [])
  {
    return $this->OSS->getObjectURL($objectName, $expires, $URLParams, $Headers, $TempKeyPolicyStatement);
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
