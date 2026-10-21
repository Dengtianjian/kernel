<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\Application\Applications;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Date;
use kernel\Foundation\FileSystem\Path;

class DiscuzXApp extends App
{
  /**
   * @var DiscuzXProvisioner
   */
  private $provisioner = null;
  public function __construct($appId)
  {
    if (!defined("CHARSET")) {
      define("CHARSET", "utf-8");
    }

    $this->boot($appId, "gstudio_kernel");

    DiscuzXLang::load("Modules/DiscuzX/Langs/" . (strtolower(CHARSET) === "gbk" ? "gbk" : "utf-8"), Path::kernel());

    //* 延迟实例化兜底：setup() 未注入时自动实例化（Request 等，下方直接写入 URI）
    $this->ensureInstances();

    if (isset($_GET['uri'])) {
      $this->request->uri(addslashes(trim($_GET['uri'])));
    } else {
      $this->request->uri("/");
    }

    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\ExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Foundation\Exception\ExceptionHandler::handle", E_ALL);
  }
  public function hook($uri)
  {
    //* 延迟实例化兜底：setup() 未注入时自动实例化（Request 等）
    $this->ensureInstances();

    $this->request->uri($uri);
  }
  /**
   * @return DiscuzXProvisioner
   */
  function provisioner()
  {
    if (!$this->provisioner) {
      $this->provisioner = new DiscuzXProvisioner();
    }

    return $this->provisioner;
  }
}
