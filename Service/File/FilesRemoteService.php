<?php

namespace kernel\Service\File;

use kernel\Controller\Main\Files\FilesRemote as FilesRemoteNamespace;
use kernel\Foundation\File\FilesRemote;
use kernel\Foundation\Router;
use kernel\Foundation\Service;

class FilesRemoteService extends Service
{
  /**
   * 文件远程实例
   *
   * @var FilesRemote
   */
  protected static $FileRemoteInstance = null;
  static function useService($driver = null)
  {
    self::$FileRemoteInstance = new FilesRemote($driver);

    // Router::get("filesRemote/{fileId:.+?}/preview", FilesRemoteNamespace\FilesRemoteAccessFileController::class);
    // Router::get("filesRemote/{fileId:.+?}/download", FilesRemoteNamespace\FilesRemoteDownloadFileController::class);
    // Router::get("filesRemote/{fileId:.+?}", FilesRemoteNamespace\FilesRemoteGetFileController::class);
    // Router::post("filesRemote/{fileId:.+?}/upload/auth", FilesRemoteNamespace\FilesRemoteGetUploadAuthController::class);
    // Router::delete("filesRemote/{fileId:.+?}", FilesRemoteNamespace\FilesRemoteDeleteFileController::class);
  }
  static function getFileAuth($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return self::$FileRemoteInstance->getFileAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod);
  }
  static function getFileInfo($FileKey)
  {
    return self::$FileRemoteInstance->getFileInfo($FileKey);
  }
  static function getFilePreviewURL($FileKey, $URLParams = [])
  {
    return self::$FileRemoteInstance->getFilePreviewURL($FileKey, $URLParams);
  }
  static function getFileDownloadURL($FileKey, $URLParams = [])
  {
    return self::$FileRemoteInstance->getFileDownloadURL($FileKey, $URLParams);
  }
  static function deleteFile($FileKey)
  {
    return self::$FileRemoteInstance->deleteFile($FileKey);
  }
}
