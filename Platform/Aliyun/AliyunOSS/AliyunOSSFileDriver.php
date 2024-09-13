<?php

namespace kernel\Platform\Aliyun\AliyunOSS;

use kernel\Foundation\File\Driver\FileStorageDriver;
use kernel\Foundation\File\FileInfoData;
use kernel\Foundation\File\FileManager;

class AliyunOSSFileDriver extends FileStorageDriver
{
  /**
   * OSS服务实例
   *
   * @var AliyunOSSService
   */
  protected $OSSInstance = null;

  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $SignatureKey, $Record = TRUE, $RoutePrefix = "files", $BaseURL = F_BASE_URL)
  {
    $this->OSSInstance = new AliyunOSSService($SecretId, $SecretKey, $Region, $Bucket);

    parent::__construct($SignatureKey, $Record, $RoutePrefix, $BaseURL);
  }
  public function uploadFile($File, $fileKey = null, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::AUTHENTICATED_READ)
  {
    $remoteFileKey = $fileKey;
    if ($this->verifyRequestAuth($fileKey) !== TRUE) {
      return $this->break(403, "uploadFile:403001", "抱歉，您没有上传该文件的权限");
    }
    if ($this->FileAuthorizationVerification($fileKey, $ACL, $OwnerId, "write") === FALSE) {
      return $this->break(403, "uploadFile:403002", "抱歉，您没有上传该文件的权限");
    }

    $FileKeyPathInfo = pathinfo($fileKey);
    $TempFileInfo = FileManager::upload($File, $this->fileKeyRemoteIdentificationPrefix ?: 'RemoteTemp', $FileKeyPathInfo['basename']);

    $this->OSSInstance->upload($remoteFileKey, $TempFileInfo['filePath']);

    $FileInfo = [
      "key" => $fileKey,
      "sourceFileName" => $TempFileInfo['sourceFileName'],
      "path" => $FileKeyPathInfo['dirname'],
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
      $this->filesModel->add($fileKey, $FileInfo['sourceFileName'], $FileInfo['name'], $FileInfo['path'], $FileInfo['size'], $FileInfo['extension'], $OwnerId, $ACL, true, $BelongsId, $BelongsType, $FileInfo['width'], $FileInfo['height']);
    }

    if (file_exists($TempFileInfo['filePath'])) {
      unlink($TempFileInfo['filePath']);
    }

    return new FileInfoData($FileInfo);
  }
  public function getFileRemoteAuth($Expires = 1800, $FileKey = null, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = false)
  {
    return $this->OSSInstance->getFileAuth($Expires);
  }
  public function deleteFile($FileKey)
  {
    $FileInfo = $this->getFileInfo($FileKey);
    if ($this->error) return $this->return();
    if ($this->FileAuthorizationVerification($FileKey, $FileInfo->accessControl, $FileInfo->ownerId) === FALSE) {
      return $this->break(403, 403001, "抱歉，您无权删除该文件");
    }

    $COSDeletedResult = $this->OSSInstance->deleteFile($FileKey);
    if ($COSDeletedResult && $this->filesModel) {
      $this->filesModel->remove(true, $FileKey);
    }
    if ($COSDeletedResult === false) {
      return $this->break(500, 500, "删除失败，请稍后重试");
    }

    return TRUE;
  }
  // public function getFileInfo($FileKey, $AccessControl = TRUE) {}
  public function getFileRemotePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $this->OSSInstance->getFilePreviewURL($FileKey, $Expires);
  }
  public function getFileRemoteDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $this->OSSInstance->getFilePreviewURL($FileKey, $Expires);
  }
}
