<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Validate\ValidateRules;

/**
 * 获取文件授权信息  
 * 该控制器并不会自动注册，请根据实际业务需求覆写当前控制器，并且覆盖路由
 */
class GetFileAuthController extends FileBaseController
{
  public function data()
  {
    // return $this->driver->getFileAuth($FileKey, 1800);
  }
}
