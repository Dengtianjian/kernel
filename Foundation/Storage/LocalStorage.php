<?php

namespace kernel\Foundation\Storage;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\HTTP\URL;

class LocalStorage extends AbstractStorage
{
  public function deleteFile($fileKey)
  {
    $FileInfo = $this->getFile($fileKey);
    if (!$FileInfo) return $this->return();

    $DeletedResult = FileManager::deleteFile(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $fileKey)));

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
        if ($this->verifyRequestAuth($fileKey) === FALSE) {
          return $this->break(403, "getFile:403003", "抱歉，您无权获取该文件信息");
        }
        if ($this->accessAuthozationVerification($fileKey, $fileInfo['accessControl'], $fileInfo['ownerId']) === FALSE) {
          return $this->break(403, "getFile:403002", "抱歉，您无权获取该文件信息");
        }
      }
    } else {
      $fileInfo = FileManager::getFileInfo(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $fileKey)));
      if (!$fileInfo) {
        return $this->break(404, 404, "文件不存在");
      };
    }

    $fileInfo['key'] = $fileKey;
    $fileInfo['remote'] = false;
    $fileInfo['url'] = $this->getFilePreviewURL($fileKey);
    $fileInfo['previewURL'] = $this->getFilePreviewURL($fileKey);
    $fileInfo['downloadURL'] = $this->getFileDownloadURL($fileKey);
    $fileInfo['transferPreviewURL'] = $this->getFilePreviewURL($fileKey);
    $fileInfo['transferDownloadURL'] = $this->getFileDownloadURL($fileKey);

    return new StorageFileInfoData($fileInfo);
  }
  public function getFileAuth($fileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get")
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
