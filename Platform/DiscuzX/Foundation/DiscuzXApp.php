<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\FileSystem\FileHelper;

class DiscuzXApp extends App
{
  public function __construct($appId)
  {
    if (!defined("CHARSET")) {
      define("CHARSET", "utf-8");
    }

    parent::__construct($appId, "gstudio_kernel");

    //* 延迟实例化兜底：setup() 未注入时自动实例化（Request 等，下方直接写入 URI）
    $this->ensureInstances();

    if (isset($_GET['uri'])) {
      $this->request->URI = addslashes(trim($_GET['uri']));
    } else {
      $this->request->URI = "/";
    }

    //* 异常处理
    \set_exception_handler("kernel\Platform\DiscuzX\Foundation\DiscuzXExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Platform\DiscuzX\Foundation\DiscuzXExceptionHandler::handle", E_ALL);
  }
  public function hook($uri)
  {
    //* 延迟实例化兜底：setup() 未注入时自动实例化（Request 等）
    $this->ensureInstances();

    $this->request->URI = $uri;
  }
  /**
   * 初始化以及定义常量
   *
   * FileSystem 路径无需在此设置：kernelRoot 为本类所在内核目录，root 自动取 DISCUZ_ROOT（去尾斜杠），
   * appRoot/appData/appStorage/kernelDir/appDir 由两者按默认规则推导。
   *
   * @return void
   */
  protected function defineConstants()
  {
    /**
     * 根目录，绝对路径
     */
    $root = substr(DISCUZ_ROOT, 0, strlen(DISCUZ_ROOT) - 1);
    /**
     * DiscuzX插件目录
     */
    if (!defined("F_DISCUZX_PLUGIN_ROOT")) {
      define("F_DISCUZX_PLUGIN_ROOT", FileHelper::combinedFilePath($root, "source", "plugin"));
    }
    /**
     * DiscuzX插件目录，相对路径
     */
    if (!defined("F_DISCUZX_PLUGIN")) {
      define("F_DISCUZX_PLUGIN", FileHelper::combinedFilePath("source", "plugin"));
    }
    /**
     * DiscuzX Data目录
     */
    if (!defined("F_DISCUZX_DATA")) {
      define("F_DISCUZX_DATA", FileHelper::combinedFilePath($root, "data"));
    }
    /**
     * DiscuzX Data下存放插件数据的目录
     */
    if (!defined("F_DISCUZX_DATA_PLUGIN")) {
      define("F_DISCUZX_DATA_PLUGIN", FileHelper::combinedFilePath($root, "data", "plugindata", App::id()));
    }

    global $_G;
    /**
     * APP的URL地址
     */
    if (!defined("F_BASE_URL")) {
      define("F_BASE_URL", substr($_G['siteurl'], 0, strlen($_G['siteurl']) - 1));
    }
  }
}
