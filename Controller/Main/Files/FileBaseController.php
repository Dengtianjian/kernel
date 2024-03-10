<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\File\Driver\AbstractFileDriver;
use kernel\Foundation\File\Driver\AbstractFileStorageDriver;

class FileBaseController extends AuthController
{
  /**
   * 文件驱动
   *
   * @var AbstractFileDriver|AbstractFileStorageDriver
   */
  protected $driver = null;
  public function __construct($R, AbstractFileDriver $Driver)
  {
    parent::__construct($R);

    $this->driver = $Driver;
  }
}
