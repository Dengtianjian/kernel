<?php

namespace kernel\Controller\Main\Files;

/**
 * 下载文件
 *
 * 公共流程见父类 {@see FileOutputController}；本类只声明"下载"这一输出方式
 * （本地 attachment 输出；远程资源同样按协议选择服务端代理或 302 跳转，走代理时**强制 attachment**，
 * 不会因为文件是图片就变成内联打开）。
 *
 * @package kernel\Controller\Main\Files
 */
class DownloadFileController extends FileOutputController
{
  /**
   * 下载模式
   *
   * @var boolean
   */
  protected $download = true;

  /**
   * 下载指定文件
   *
   * @param string|null $fileKey 文件键
   * @return mixed 文件 / 代理 / 跳转响应，或错误响应
   */
  public function data($fileKey = null)
  {
    return $this->output($fileKey);
  }
}
