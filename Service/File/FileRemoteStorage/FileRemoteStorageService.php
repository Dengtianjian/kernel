<?php

namespace kernel\Service\File\FileRemoteStorage;

use kernel\Service\File\FileStorageService;

class FileRemoteStorageService extends FileStorageService
{
  /**
   * 远程存储服务实例
   *
   * @var object
   */
  protected static $RemoteStorageInstance = null;

  static function useService()
  {
    parent::useService();


  }
}
