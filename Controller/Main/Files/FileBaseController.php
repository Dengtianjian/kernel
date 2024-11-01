<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Storage\LocalStorage;
use kernel\Foundation\Storage\AbstractStorage;
use kernel\Platform\Aliyun\AliyunOSS\AliyunOSSStorage;
use kernel\Service\StorageService;

class FileBaseController extends AuthController
{
  /**
   * 文件驱动
   *
   * @var LocalStorage|AbstractStorage|AliyunOSSStorage
   */
  protected $platform = null;
  public function __construct($R)
  {
    parent::__construct($R);

    $this->platform = StorageService::getPlatform();
  }
}
