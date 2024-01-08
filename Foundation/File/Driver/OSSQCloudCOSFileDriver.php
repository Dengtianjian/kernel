<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileManager;
use kernel\Service\OSS\OSSQcloudCosService;

class OSSQCloudCOSFileDriver extends FileStorageDriver
{
  /**
   * COS实例
   *
   * @var OSSQcloudCosService
   */
  protected $COSInstance = null;
  /**
   * 实例化腾讯云COS存储驱动
   *
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   * @param string $SignatureKey 本地存储签名秘钥
   * @param string $Record 存储的文件信息是否存入数据库
   */
  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $SignatureKey, $Record = TRUE)
  {
    parent::__construct(true, $SignatureKey, $Record);

    $this->COSInstance = new OSSQcloudCosService($SecretId, $SecretKey, $Region, $Bucket);
  }
  public function uploadFile($File, $FileKey = null, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::PRIVATE)
  {
    $FileKeyPathInfo = pathinfo($FileKey);
    $TempFileInfo = FileManager::upload($File, "OSSTemp", $FileKeyPathInfo['basename']);
    $this->COSInstance->upload($FileKey, $TempFileInfo['filePath']);

    $FileInfo = [
      "fileKey" => $FileKey,
      "sourceFileName" => $TempFileInfo['sourceFileName'],
      "path" =>  $FileKeyPathInfo['dirname'],
      "filePath" => $TempFileInfo['dirname'],
      "fileName" => $FileKeyPathInfo['basename'],
      "extension" => $FileKeyPathInfo['extension'],
      "fileSize" => $TempFileInfo['size'],
      "width" => $TempFileInfo['width'],
      "height" => $TempFileInfo['height'],
      "remote" => true
    ];
    $this->filesModel->add($FileKey, $FileInfo['sourceFileInfo'], $FileInfo['fileName'], $FileInfo['path'], $FileInfo['fileSize'], $FileInfo['extension'], $OwnerId, $ACL, true, $BelongsId, $BelongsType, $FileInfo['width'], $FileInfo['height']);

    return $this->return->success($FileInfo);
  }
  public function getFileRemoteAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = TRUE)
  {
    return $this->COSInstance->getFileAuth($FileKey, $Expires, $HTTPMethod, $URLParams, $Headers);
  }
  public function deleteFile($FileKey)
  {
    $COSDeletedResult = $this->COSInstance->deleteFile($FileKey);
    if ($COSDeletedResult && $this->filesModel) {
      $this->filesModel->where("key", $FileKey);
    }
    if ($COSDeletedResult === false) {
      return $this->return->error(500, 500, "删除失败，请稍后重试");
    }

    return $this->return->success(true);
  }
  public function getFileInfo($FileKey)
  {
    $COSFileInfo = [
      "fileKey" => $FileKey,
      "key" => $FileKey,
      "path" => null,
      "fileName" => null,
      "extension" => null,
      "size" => null,
      "filePath" => null,
      "width" => null,
      "height" => null,
      'remote' => true
    ];
    if ($this->filesModel) {
      $fileInfo = parent::getFileInfo($FileKey);
      if (!$fileInfo->error) return $fileInfo;
      $fileInfo = $fileInfo->getData();
      if (!$fileInfo['remote']) {
        return $this->return->success($fileInfo);
      }

      $COSFileInfo = array_merge($COSFileInfo, $fileInfo);
    } else {
      $PathInfo = pathinfo($FileKey);
      $COSFileInfo['path'] = $PathInfo['dirname'];
      $COSFileInfo['fileName'] = $PathInfo['basename'];
      $COSFileInfo['extension'] = $PathInfo['extension'];
      $COSFileInfo['filePath'] = $PathInfo['dirname'];
    }

    return $this->return->success($COSFileInfo);
  }
  public function getImageInfo($FileKey)
  {
    return $this->return->success($this->COSInstance->getImageInfo($FileKey));
  }
  /**
   * 获取文件下载直链
   *
   * @param string $FileKey 对象名称
   * @param array $URLParams URL的query参数
   * @param integer $Expires 签名有效期
   * @param boolean $WithSignature 是否携带签名
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string HTTPS协议的对象访问链接地址
   */
  public function getFileRemotePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $TempKeyPolicyStatement = [])
  {
    return $this->COSInstance->getFilePreviewURL($FileKey, $URLParams, [], $Expires, $WithSignature, $TempKeyPolicyStatement);
  }
  /**
   * 获取文件预览直链
   *
   * @param string $FileKey 对象名称
   * @param array $URLParams URL的query参数
   * @param integer $Expires 签名有效期
   * @param boolean $WithSignature 是否携带签名
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string HTTPS协议的对象访问链接地址
   */
  public function getFileRemoteDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $TempKeyPolicyStatement = [])
  {
    return $this->COSInstance->getFileDownloadURL($FileKey, $URLParams, [], $Expires, $WithSignature, $TempKeyPolicyStatement);
  }
}
