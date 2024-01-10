<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileInfoData;
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
   * 远程文件名前缀标识，值为NULL或者FALSE就是不增加远程前缀标识  
   * 为何存在：用于区分不入库的文件是存放在远程存储库还是本地磁盘  
   * 辨别传入的文件名是否是远程文件，适用于不存入数据库的文件信息，因为不存入数据库无法区分是本地文件还是远程存储文件，所以特地增加前缀去区分。  
   * 例如路由files/{fileKey}，那么实际fileKey就是{FileKeyRemotePrefix}/{fileKey}，当访问远程存储时会自动去掉FileKeyRemotePrefix  
   * 假设在腾讯云COS存储了一个文件a.png，远程存储标识前缀是remote，预览URI就是files/remote/a.png/preview，获取到的远程存储访问的URI是bucket.ap-region.cos.ap-guangzhou.myqcloud.com/a.png，因为remote这个远程标识只是用于框架区分，获取实际URL时会去掉。
   *
   * @var string
   */
  protected $fileKeyRemoteIdentificationPrefix = NULL;
  /**
   * 实例化腾讯云COS存储驱动
   *
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶名称：bucketName-appid, 如 test-125000000
   * @param string $SignatureKey 本地存储签名秘钥
   * @param string $Record 存储的文件信息是否存入数据库
   * @param string $FileKeyRemoteIdentificationPrefix 远程文件名前缀标识，值为NULL或者FALSE就是不增加远程前缀标识  
   * @param string $RoutePrefix 路由前缀
   */
  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $SignatureKey, $Record = TRUE, $FileKeyRemoteIdentificationPrefix = NULL, $RoutePrefix = "files")
  {
    parent::__construct(true, $SignatureKey, $Record, $RoutePrefix);

    $this->COSInstance = new OSSQcloudCosService($SecretId, $SecretKey, $Region, $Bucket);
    $this->fileKeyRemoteIdentificationPrefix = $FileKeyRemoteIdentificationPrefix;
  }
  public function uploadFile($File, $fileKey = null, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::PRIVATE)
  {
    $remoteFileKey = $fileKey;
    if ($this->fileKeyRemoteIdentificationPrefix) {
      if (strpos($fileKey, $this->fileKeyRemoteIdentificationPrefix) === false) {
        $fileKey = "{$this->fileKeyRemoteIdentificationPrefix}/{$fileKey}";
      }
    }

    $FileKeyPathInfo = pathinfo($fileKey);
    $TempFileInfo = FileManager::upload($File, $this->fileKeyRemoteIdentificationPrefix ?: 'RemoteTemp', $FileKeyPathInfo['basename']);

    $this->COSInstance->upload($remoteFileKey, $TempFileInfo['filePath']);

    $FileInfo = [
      "key" => $fileKey,
      "sourceFileName" => $TempFileInfo['sourceFileName'],
      "path" =>  $FileKeyPathInfo['dirname'],
      "filePath" => $TempFileInfo['dirname'],
      "name" => $FileKeyPathInfo['basename'],
      "extension" => $FileKeyPathInfo['extension'],
      "size" => $TempFileInfo['size'],
      "width" => $TempFileInfo['width'],
      "height" => $TempFileInfo['height'],
      "remote" => true
    ];

    if ($this->filesModel) {
      if ($this->filesModel->existItem($fileKey)) {
        $this->filesModel->remove($fileKey);
      }
      $this->filesModel->add($fileKey, $FileInfo['sourceFileName'], $FileInfo['fileName'], $FileInfo['path'], $FileInfo['fileSize'], $FileInfo['extension'], $OwnerId, $ACL, true, $BelongsId, $BelongsType, $FileInfo['width'], $FileInfo['height']);
    }

    if (file_exists($TempFileInfo['filePath'])) {
      unlink($TempFileInfo['filePath']);
    }

    return new FileInfoData($FileInfo);
  }
  public function getFileAuth($fileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = false)
  {
    if ($this->fileKeyRemoteIdentificationPrefix) {
      if (strpos($fileKey, $this->fileKeyRemoteIdentificationPrefix) === false) {
        $fileKey = "{$this->fileKeyRemoteIdentificationPrefix}/{$fileKey}";
      }
    }

    return parent::getFileAuth($fileKey, $Expires, $URLParams, $Headers, $HTTPMethod, $toString);
  }
  public function getFileRemoteAuth($fileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = TRUE)
  {
    return $this->COSInstance->getFileAuth($fileKey, $Expires, $HTTPMethod, $URLParams, $Headers);
  }
  public function deleteFile($fileKey)
  {
    $remoteFileKey = $fileKey;
    if ($this->fileKeyRemoteIdentificationPrefix) {
      if (strpos($fileKey, $this->fileKeyRemoteIdentificationPrefix) !== false) {
        $remoteFileKey = str_replace($this->fileKeyRemoteIdentificationPrefix, "", $fileKey);
      }
    }

    $COSDeletedResult = $this->COSInstance->deleteFile($remoteFileKey);
    if ($COSDeletedResult && $this->filesModel) {
      $this->filesModel->where("key", $fileKey);
    }
    if ($COSDeletedResult === false) {
      return $this->break(500, 500, "删除失败，请稍后重试");
    }

    return TRUE;
  }
  public function getFileInfo($fileKey)
  {
    $remote = strpos($fileKey, $this->fileKeyRemoteIdentificationPrefix) !== false;

    $COSFileInfo = [
      "fileKey" => $fileKey,
      "key" => $fileKey,
      "path" => null,
      "fileName" => null,
      "extension" => null,
      "size" => null,
      "filePath" => null,
      "width" => null,
      "height" => null,
      'remote' => $remote
    ];
    if ($this->filesModel) {
      $fileInfo = parent::getFileInfo($fileKey);
      if ($this->error) return $this->return();
      if (!$fileInfo->remote) {
        return new FileInfoData($fileInfo);
      }

      $COSFileInfo = array_merge($COSFileInfo, $fileInfo);
    } else {
      if ($remote) {
        $remoteFileKey = str_replace($this->fileKeyRemoteIdentificationPrefix, "", $fileKey);
        $COSDoesExist = $this->COSInstance->doesObjectExist($remoteFileKey);
        if (!$COSDoesExist) {
          return $this->break(404, 404, "文件不存在");
        }
        $PathInfo = pathinfo($fileKey);
        $COSFileInfo['path'] = $PathInfo['dirname'];
        $COSFileInfo['name'] = $PathInfo['basename'];
        $COSFileInfo['extension'] = $PathInfo['extension'];
        $COSFileInfo['path'] = $PathInfo['dirname'];
      } else {
        return parent::getFileInfo($fileKey);
      }
    }

    return $COSFileInfo;
  }
  public function getImageInfo($FileKey)
  {
    return $this->COSInstance->getImageInfo($FileKey);
  }
  /**
   * 获取文件下载直链
   *
   * @param string $fileKey 对象名称
   * @param array $URLParams URL的query参数
   * @param integer $Expires 签名有效期
   * @param boolean $WithSignature 是否携带签名
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string HTTPS协议的对象访问链接地址
   */
  public function getFileRemotePreviewURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $TempKeyPolicyStatement = [])
  {
    if ($this->fileKeyRemoteIdentificationPrefix) {
      $fileKey = str_replace($this->fileKeyRemoteIdentificationPrefix, "", $fileKey);
    }

    return $this->COSInstance->getFilePreviewURL($fileKey, $URLParams, [], $Expires, $WithSignature, $TempKeyPolicyStatement);
  }
  /**
   * 获取文件预览直链
   *
   * @param string $fileKey 对象名称
   * @param array $URLParams URL的query参数
   * @param integer $Expires 签名有效期
   * @param boolean $WithSignature 是否携带签名
   * @param array $TempKeyPolicyStatement 临时秘钥策略描述语句
   * @return string HTTPS协议的对象访问链接地址
   */
  public function getFileRemoteDownloadURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $TempKeyPolicyStatement = [])
  {
    if ($this->fileKeyRemoteIdentificationPrefix) {
      $fileKey = str_replace($this->fileKeyRemoteIdentificationPrefix, "", $fileKey);
    }
    return $this->COSInstance->getFileDownloadURL($fileKey, $URLParams, [], $Expires, $WithSignature, $TempKeyPolicyStatement);
  }
}