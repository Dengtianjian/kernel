<?php

namespace kernel\Foundation\FileSystem\Storage;
use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\HTTP\URL;

class LocalStorage extends AbstractStorage
{
  public function deleteFile($fileKey)
  {
    $FileInfo = $this->getFile($fileKey);
    if (!$FileInfo) return $this->return();

    $DeletedResult = FileSystem::deleteFile(FileHelper::optimizedPath(FileHelper::combinedFilePath(Path::storage(), $fileKey)));

    if ($DeletedResult && $this->filesModel) {
      $this->filesModel->remove(true, $fileKey);
    }

    return $DeletedResult;
  }
  public function fileExist($fileKey)
  {
    $FileInfo = $this->getFile($fileKey);
    if (!$FileInfo) return $this->return();

    return file_exists($FileInfo->path);
  }
  public function getFile($fileKey)
  {
    $fileInfo = null;
    if ($this->filesModel) {
      $fileInfo = $this->filesModel->item($fileKey);
      if ($this->getACAuthId() != $fileInfo['ownerId']) {
        if ($this->ACLEnabled) {
          if ($this->accessAuthozationVerification($fileKey, $fileInfo['accessControl'], $fileInfo['ownerId']) === FALSE) {
            return $this->break(403, "getFile:403002", "抱歉，您无权获取该文件信息");
          }
        } else if ($this->authorizationEnabled && $this->verifyRequestAuth($fileKey) === FALSE) {
          return $this->break(403, "getFile:403003", "抱歉，您无权获取该文件信息");
        }
      }
    } else {
      if ($this->authorizationEnabled && $this->verifyRequestAuth($fileKey) === FALSE) {
        return $this->break(403, "getFile:403003", "抱歉，您无权获取该文件信息");
      }
      $fileInfo = FileSystem::getFileInfo(FileHelper::optimizedPath(FileHelper::combinedFilePath(Path::storage(), $fileKey)));

      $dirName = pathinfo($fileKey, PATHINFO_DIRNAME);
      $fileInfo['path'] = !$dirName || $dirName === '.' ? NULL : $dirName;

      if (!$fileInfo) {
        return $this->break(404, 404, "文件不存在");
      };
    }

    $fileInfo['key'] = $fileKey;
    $fileInfo['remote'] = boolval($fileInfo['remote']);
    $fileInfo['url'] = $this->getFilePreviewURL($fileKey);
    $fileInfo['previewURL'] = $this->getFilePreviewURL($fileKey);
    $fileInfo['downloadURL'] = $this->getFileDownloadURL($fileKey);
    $fileInfo['transferPreviewURL'] = $this->getFilePreviewURL($fileKey);
    $fileInfo['transferDownloadURL'] = $this->getFileDownloadURL($fileKey);

    return new StorageFileInfoData($fileInfo);
  }
  public function getFileAuth($fileKey = null, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return $this->getFileTransferAuth($fileKey, $Expires, $URLParams, $Headers, $HTTPMethod);
  }
  public function getFileSign($fileKey = null, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return $this->getFileTransferAuth($fileKey, $Expires, $URLParams, $Headers, $HTTPMethod);
  }
  public function getFilePreviewURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->getFileTransferPreviewURL($fileKey, $URLParams, $Expires, $WithSignature);
  }
  public function getFileDownloadURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->getFileTransferDownloadURL($fileKey, $URLParams, $Expires, $WithSignature);
  }
}
