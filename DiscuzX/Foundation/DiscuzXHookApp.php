<?php

namespace kernel\DiscuzX\Foundation;

use kernel\Foundation\FileSystem\FileSystem;

class DiscuzXHookApp extends DiscuzXApp
{
  /**
   * 构造：只走 Web 应用那一层（基座 + 框架零件），跳过 DiscuzXApp 的构造追加
   *
   * 刻意**不用** `parent::__construct()`：那会连带执行 DiscuzXApp 里的语言加载、
   * 请求 URI 写入与异常处理器设置。钩子应用被宿主钩子点直接实例化，`$_GET['uri']`
   * 往往还不存在，此时改写共享 Request 的 URI 会把正在处理的请求带偏。
   *
   * 显式点名 {@see \kernel\Foundation\App} 的构造函数，用意是「从 App 这一层起跳」，
   * 而不是「绕过构造函数」——基座与框架零件照常登记。
   *
   * @param string|callable|null $setup 装配类名或装配闭包；null 表示只建骨架、不装配
   * @param string $appId 应用 ID（等于插件目录名）
   */
  public function __construct($setup = null, $appId = null)
  {
    \kernel\Foundation\App::__construct($setup, $appId, "gstudio_kernel");

    //* 实例化 FileSystem（无参构造，不计算路径；路径在每次静态方法调用时自动计算）
    new FileSystem;
    //* 配置：走访问器而不是 `new Config`——后者会造出一份游离实例，
    //* 之后 `$app->config()` 又建一份，配置文件被 include 两次。
    $this->config();
  }
}
