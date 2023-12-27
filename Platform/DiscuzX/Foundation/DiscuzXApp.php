<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\File\FileHelper;

class DiscuzXApp extends App
{
  public function __construct($AppId)
  {
    if (!defined("CHARSET")) {
      define("CHARSET", "utf-8");
    }

    parent::__construct($AppId, "gstudio_kernel");
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
    $this->request->URI = $uri;
  }
  /**
   * 初始化以及定义常量
   *
   * @return void
   */
  protected function defineConstants()
  {
    /**
     * 缓存动态KEY，主要用于静态文件
     */
    if (!defined("F_CACHE_KEY")) {
      define("F_CACHE_KEY", time());
    }
    /**
     * 根目录，绝对路径
     */
    if (!defined("F_ROOT")) {
      define("F_ROOT", substr(DISCUZ_ROOT, 0, strlen(DISCUZ_ROOT) - 1));
    }
    /**
     * DiscuzX插件目录
     */
    if (!defined("F_DISCUZX_PLUGIN_ROOT")) {
      define("F_DISCUZX_PLUGIN_ROOT", FileHelper::combinedFilePath(F_ROOT, "source", "plugin"));
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
      define("F_DISCUZX_DATA", FileHelper::combinedFilePath(F_ROOT, "data"));
    }
    /**
     * KERNEL的ID，默认是“kernel”，实例化App时传入的第二个参数便是该值。该常量也是kernel目录文件夹的名称
     */
    if (!defined("F_KERNEL_ID")) {
      define("F_KERNEL_ID", $this->KernelId);
    }
    /**
     * KERNEL的根目录
     */
    if (!defined("F_KERNEL_ROOT")) {
      define("F_KERNEL_ROOT", FileHelper::combinedFilePath(F_DISCUZX_PLUGIN_ROOT, $this->KernelId));
    }
    /**
     * 当前运行的项目APPID，也是项目的文件夹名称。值取自实例化APP时传入的第一个参数
     */
    if (!defined("F_APP_ID")) {
      define("F_APP_ID", $this->AppId);
    }
    /**
     * 当前运行的项目APP根目录，绝对路径
     */
    if (!defined("F_APP_ROOT")) {
      define("F_APP_ROOT", FileHelper::combinedFilePath(F_DISCUZX_PLUGIN_ROOT, $this->AppId));
    }
    /**
     * 当前运行的项目Data目录，绝对路径
     */
    if (!defined("F_APP_DATA")) {
      define("F_APP_DATA", FileHelper::combinedFilePath(F_APP_ROOT, "Data"));
    }
    define("F_APP_STORAGE", FileHelper::combinedFilePath(F_APP_ROOT, "Storage"));
    /**
     * DiscuzX Data下存放插件数据的目录
     */
    if (!defined("F_DISCUZX_DATA_PLUGIN")) {
      define("F_DISCUZX_DATA_PLUGIN", FileHelper::combinedFilePath(F_ROOT, "data", "plugindata", F_APP_ID));
    }
    /**
     * 内核目录，相对路径
     */
    if (!defined("F_KERNEL_DIR")) {
      define("F_KERNEL_DIR", FileHelper::combinedFilePath(F_DISCUZX_PLUGIN, F_KERNEL_ID));
    }
    /**
     * APP目录，相对路径
     */
    if (!defined("F_APP_DIR")) {
      define("F_APP_DIR", FileHelper::combinedFilePath(F_DISCUZX_PLUGIN, F_APP_ID));
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
