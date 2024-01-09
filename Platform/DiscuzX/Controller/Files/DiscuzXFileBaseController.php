<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\File\Driver\AbstractFileDriver;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class DiscuzXFileBaseController extends DiscuzXController
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
