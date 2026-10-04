<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\FileSystem\Path;

class DiscuzXApp extends App
{
  public function __construct($appId)
  {
    if (!defined("CHARSET")) {
      define("CHARSET", "utf-8");
    }

    //* 注册当前应用实例（等价 parent::__construct 的注册部分，但不安装内核的全局异常/错误处理器）
    $this->register($appId, "gstudio_kernel");

    include_once(Path::join(Path::kernelRoot(), "Foundation", "Common.php"));

    DiscuzXLang::load("Modules/DiscuzX/Langs/" . (strtolower(CHARSET) === "gbk" ? "gbk" : "utf-8"), Path::kernelRoot());

    //* 延迟实例化兜底：setup() 未注入时自动实例化（Request 等，下方直接写入 URI）
    $this->ensureInstances();

    if (isset($_GET['uri'])) {
      $this->request->uri(addslashes(trim($_GET['uri'])));
    } else {
      $this->request->uri("/");
    }

    //* 异常处理
    \set_exception_handler("kernel\Modules\DiscuzX\Foundation\DiscuzXExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Modules\DiscuzX\Foundation\DiscuzXExceptionHandler::handle", E_ALL);
  }
  public function hook($uri)
  {
    //* 延迟实例化兜底：setup() 未注入时自动实例化（Request 等）
    $this->ensureInstances();

    $this->request->uri($uri);
  }
}
