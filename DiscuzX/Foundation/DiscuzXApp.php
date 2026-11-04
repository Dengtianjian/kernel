<?php

namespace kernel\DiscuzX\Foundation;

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

    DiscuzXLang::load("DiscuzX/Langs/" . (strtolower(CHARSET) === "gbk" ? "gbk" : "utf-8"), Path::kernel());

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
  /**
   * DiscuzX 环境覆写：物理目录统一带 `gstudio_` 前缀
   *
   * 基础实现返回命名空间第一段；DiscuzX 下目录名统一带 `gstudio_` 前缀，
   * 因此解析出的 appId 若未带该前缀则自动补上（如 `kernel` → `gstudio_kernel`），
   * 已带前缀（`gstudio_download` 等）原样返回。
   *
   * @param object|string $class 对象、完整类名或命名空间字符串
   * @return string|null 应用 ID（DiscuzX 目录名，带 gstudio_ 前缀）
   */
  public static function appIdFromNamespace($class)
  {
    $appId = parent::appIdFromNamespace($class);

    if ($appId === null || $appId === "") {
      return $appId;
    }

    return strpos($appId, "gstudio_") === 0 ? $appId : "gstudio_" . $appId;
  }
}
