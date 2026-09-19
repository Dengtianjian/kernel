<?php

namespace kernel\Platform\DiscuzX\Foundation;

class DiscuzXHookApp extends DiscuzXApp
{
  public function __construct($AppId)
  {
    $this->AppId = $AppId;
    $this->KernelId = "gstudio_kernel";
    //* 定义常量
    $this->defineConstants();
    //* 初始化配置
    $this->initConfig();
  }
}
