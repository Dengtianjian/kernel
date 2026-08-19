<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\Config;
use kernel\Foundation\FileSystem\FileSystem;

class DiscuzXHookApp extends DiscuzXApp
{
  public function __construct($appId)
  {
    //* 注册当前 App 实例（App::id()/App::kernelId() 从该实例读取）
    App::$currentApp = $this;
    $this->appId = $appId;
    $this->kernelId = "gstudio_kernel";
    //* 定义常量
    $this->defineConstants();
    //* 实例化 FileSystem（无参构造，不计算路径；路径在每次静态方法调用时自动计算）
    new FileSystem;
    //* 初始化配置
    new Config;
  }
}
