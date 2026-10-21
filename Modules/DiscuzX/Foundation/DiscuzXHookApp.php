<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\Application\Applications;
use kernel\Foundation\Config;
use kernel\Foundation\Data\Date;
use kernel\Foundation\FileSystem\FileSystem;

class DiscuzXHookApp extends DiscuzXApp
{
  public function __construct($appId)
  {
    //* 初始化应用身份：复刻 App::__construct() 开头的身份初始化（本类不调用父构造，
    //* 只做身份 + FileSystem + Config 三件事）—— 改动时需与 App::__construct() 同步。
    //* 第三个参数 true：插件 App 接管默认应用位置
    $this->id = $appId;
    $this->kernelId = "gstudio_kernel";
    $this->startTime = Date::milliseconds();
    Applications::register($this, $appId, true);
    //* 实例化 FileSystem（无参构造，不计算路径；路径在每次静态方法调用时自动计算）
    new FileSystem;
    //* 初始化配置
    new Config;
  }
}
