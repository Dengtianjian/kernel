<?php

namespace kernel\Controller\Main\Files;

/**
 * 预览文件
 *
 * 公共流程见父类 {@see FileOutputController}；本类只声明"预览"这一输出方式
 * （本地 inline 输出、带 `max-age=43200` 缓存；远程资源按协议选择服务端代理或 302 跳转）。
 *
 * @package kernel\Controller\Main\Files
 */
class PrewiewFileController extends FileOutputController
{
  /**
   * 预览指定文件
   *
   * @param string|null $fileKey 文件键
   * @return mixed 文件 / 代理 / 跳转响应，或错误响应
   */
  public function data($fileKey = null)
  {
    return $this->output($fileKey);
  }
}
