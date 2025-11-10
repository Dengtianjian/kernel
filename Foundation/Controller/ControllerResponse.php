<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\Response\ResponseDownload;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Foundation\HTTP\Response\ResponsePagination;
use kernel\Foundation\HTTP\Response\ResponseView;

class ControllerResponse extends Response
{
  /**
   * 文件类型响应
   *
   * @param string $filePath 下载的文件绝对路径
   * @param ?string $downloadFileName 下载到下载者设备时保存的文件名
   * @param int $imageQuality 如果是图片类型文件，该值将影响输出的图片质量
   * @param string $cacheControl HTTP 缓存控制属性值
   * @param string $httpExpires HTTP 资源过期时间，秒级时间戳
   * @return ResponseFile
   */
  function file($filePath, $downloadFileName = null, $imageQuality = null, $cacheControl = "no-cache", $httpExpires = null)
  {
    return new ResponseFile(getApp()->request(), $filePath, $downloadFileName, $imageQuality, $cacheControl, $httpExpires);
  }
  /**
   * 下载文件响应
   *
   * @param string $filePath 下载的文件绝对路径
   * @param ?string $downloadFileName 下载到下载者设备时保存的文件名
   * @param boolean|int $rateLimit 下载速率限制，如果值不为false，即开启了下载速率，kb/秒，单位是：千字节
   * @return ResponseDownload
   */
  function download($filePath, $downloadFileName = null, $rateLimit = false)
  {
    return new ResponseDownload(getApp()->request(), $filePath, $downloadFileName, $rateLimit);
  }
  /**
   * 响应分页列表
   *
   * @param integer $total 数据总量
   * @param mixed $data 数据
   * @return ResponsePagination
   */
  function list($total, $data = null)
  {
    return new ResponsePagination(getApp()->request(), $total, $data);
  }
  /**
   * 视图响应
   *
   * @param string $viewFile 渲染的视图文件，相对于$viewFileBaseDir目录
   * @param array $viewData 渲染的数据
   * @param string $viewFileBaseDir 视图文件所在的目录，相对于根目录
   * @param string $templateId 模板ID，用于缓存模板
   * @param string $viewFileDir 视图文件根目录，默认是基于F_APP_ROOT的，也就是当前项目的根目录，但是有时候可能需要渲染别的项目的视图文件，可通过该参数来修改
   * @return ResponseView
   */
  function view($viewFile, $viewData = [], $viewFileBaseDir = "Views", $templateId = "page", $viewFileDir = null)
  {
    return new ResponseView($viewFile, $viewData, $viewFileBaseDir, $templateId, $viewFileDir);
  }
}
