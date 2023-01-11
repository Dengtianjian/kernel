<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Platform\DiscuzX\Middleware\GlobalDiscuzXMiddleware;
use kernel\Platform\DiscuzX\Middleware\GlobalDiscuzXMultipleEncodeMiddleware;

class DiscuzXApp extends App
{
  public function __construct($AppId,  $KernelId = "kernel")
  {
    if (!defined("CHARSET")) {
      define("CHARSET", "utf-8");
    }
    // $this->setMiddlware(GlobalDiscuzXMiddleware::class);
    // $this->setMiddlware(GlobalDiscuzXMultipleEncodeMiddleware::class);
    parent::__construct($AppId, $KernelId);

    //* 异常处理
    \set_exception_handler("kernel\Platform\DiscuzX\Foundation\DiscuzXExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Platform\DiscuzX\Foundation\DiscuzXExceptionHandler::handle", E_ALL);
  }
  public function hook($uri)
  {
    $this->request->URI = $uri;
  }
}
