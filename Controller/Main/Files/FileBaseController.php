<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\File\Driver\AbstractFileDriver;

class FileBaseController extends AuthController
{
  /**
   * 文件驱动
   *
   * @var AbstractFileDriver
   */
  protected $driver = null;
  public function __construct($R, AbstractFileDriver $Driver)
  {
    parent::__construct($R);

    $this->driver = $Driver;
  }
}
