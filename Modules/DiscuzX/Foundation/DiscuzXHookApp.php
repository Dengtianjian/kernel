<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\Config;
use kernel\Foundation\FileSystem\FileSystem;

class DiscuzXHookApp extends DiscuzXApp
{
  public function __construct($appId)
  {
    $this->boot($appId, "gstudio_kernel");

    //* 实例化 FileSystem（无参构造，不计算路径；路径在每次静态方法调用时自动计算）
    new FileSystem;
    //* 初始化配置
    new Config;
  }
}
