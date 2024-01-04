<?php

namespace kernel\Service\File;

use kernel\Foundation\File\FileStorage;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Service\File\FileStorageService;

class FileRemoteStorageService extends FileStorageService
{
  /**
   * 文件存储实例
   *
   * @var object
   */
  protected static $FileStorageInstance = null;
}
