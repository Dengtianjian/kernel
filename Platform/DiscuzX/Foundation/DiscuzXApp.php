<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\File;
use kernel\Platform\DiscuzX\Middleware\GlobalDiscuzXMiddleware;
use kernel\Platform\DiscuzX\Middleware\GlobalDiscuzXMultipleEncodeMiddleware;

class DiscuzXApp extends App
{
  public function __construct($AppId)
  {
    if (!defined("CHARSET")) {
      define("CHARSET", "utf-8");
    }
    // $this->setMiddlware(GlobalDiscuzXMiddleware::class);
    // $this->setMiddlware(GlobalDiscuzXMultipleEncodeMiddleware::class);
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
    define("F_CACHE_KEY", time());
    /**
     * 根目录，绝对路径
     */
    define("F_ROOT", substr(DISCUZ_ROOT, 0, strlen(DISCUZ_ROOT) - 1));
    /**
     * DiscuzX插件目录
     */
    define("F_DISCUZX_PLUGIN", File::genPath(F_ROOT, "source", "plugin"));
    /**
     * DiscuzX Data目录
     */
    define("F_DISCUZX_DATA", File::genPath(F_ROOT, "data"));
    /**
     * KERNEL的ID，默认是“kernel”，实例化App时传入的第二个参数便是该值。该常量也是kernel目录文件夹的名称
     */
    define("F_KERNEL_ID", $this->KernelId);
    /**
     * KERNEL的根目录
     */
    define("F_KERNEL_ROOT", File::genPath(F_DISCUZX_PLUGIN, $this->KernelId));
    /**
     * 当前运行的项目APPID，也是项目的文件夹名称。值取自实例化APP时传入的第一个参数
     */
    define("F_APP_ID", $this->AppId);
    /**
     * 当前运行的项目APP根目录，绝对路径
     */
    define("F_APP_ROOT", File::genPath(F_DISCUZX_PLUGIN, $this->AppId));
    /**
     * 当前运行的项目Data目录，绝对路径
     */
    define("F_APP_DATA", File::genPath(F_APP_ROOT, "Data"));

    $KernelRelativePath = "";
    $AppRelativePath = "";
    if (!function_exists("array_key_last")) {
      function array_key_last($arr)
      {
        $keys = array_keys($arr);
        return $keys[count($keys) - 1];
      }
    }
    //* kernel和app的两个绝对路径对比，获取相对路径
    if (F_KERNEL_ROOT === F_APP_ROOT) {
      $KernelDirs = explode(DIRECTORY_SEPARATOR, F_KERNEL_ROOT);
      $AppRelativePath = $KernelRelativePath = $KernelDirs[array_key_last($KernelDirs)];
    } else {
      $KernelDirs = explode(DIRECTORY_SEPARATOR, F_KERNEL_ROOT);
      $AppDirs = explode(DIRECTORY_SEPARATOR, F_APP_ROOT);

      $kernelDiffStartIndex = 0;
      $appDiffStartIndex = 0;

      foreach ($KernelDirs as $Index => $Dir) {
        if (isset($AppDirs[$Index])) {
          $kernelDiffStartIndex = $Index;
          $appDiffStartIndex = $Index;
        } else {
          $kernelDiffStartIndex = $Index;
        }
      }

      $KernelRelativePath = $KernelDirs[$kernelDiffStartIndex];
      $AppRelativePath = $AppDirs[$appDiffStartIndex];
    }

    /**
     * 内核目录，相对路径
     */
    define("F_KERNEL_DIR", $KernelRelativePath);
    /**
     * APP目录，相对路径
     */
    define("F_APP_DIR", $AppRelativePath);

    global $_G;
    /**
     * APP的URL地址
     */
    define("F_BASE_URL", substr($_G['siteurl'], 0, strlen($_G['siteurl']) - 1));
  }
}