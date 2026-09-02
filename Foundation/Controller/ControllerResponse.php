<?php

namespace kernel\Foundation\Controller;


use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\Response\ResponseDownload;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Foundation\HTTP\Response\ResponsePagination;
use kernel\Foundation\HTTP\Response\ResponseRedirect;
use kernel\Foundation\HTTP\Response\ResponseView;

/**
 * 控制器响应工厂
 *
 * 继承 {@see Response}，作为控制器内部快速构建各类具体响应对象的工厂类。
 * 框架在 `Controller::__construct` 中将其初始化为 `$this->response` 的默认实例，
 * 控制器可通过 `success()`/`fail()` 复用该实例，也可在 `data()` 中调用以下工厂方法
 * 生成「独立响应对象」（如文件下载、分页、视图），由其作为最终结果输出。
 *
 * 所有工厂方法均自动注入当前请求（`getApp()->request()`）作为响应上下文，
 * 因此无需手动传入 Request 即可直接使用。
 */
class ControllerResponse extends Response
{
  /**
   * 文件类型响应
   *
   * 用于直接输出文件内容（含图片缩略图、Range 分片下载等），支持浏览器内联或强制下载。
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
  function pagination($total, $data = null)
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
   * @param string $viewFileDir 视图文件根目录，默认是基于Path::root()的，也就是当前项目的根目录，但是有时候可能需要渲染别的项目的视图文件，可通过该参数来修改
   * @return ResponseView
   */
  function view($viewFile, $viewData = [], $viewFileBaseDir = "Views", $templateId = "page", $viewFileDir = null)
  {
    return new ResponseView($viewFile, $viewData, $viewFileBaseDir, $templateId, $viewFileDir);
  }
  /**
   * 重定向响应
   *
   * 返回 `ResponseRedirect` 实例，用于控制器内快速发起跳转（Laravel 风格）。
   * 返回值需由 `data()` 方法 `return`，框架据此输出 3xx 响应。
   *
   * @param string|null $to          初始目标地址（可选，也可后续链式 `->route()`/`->back()` 等）
   * @param integer     $statusCode HTTP 状态码，默认 302
   * @return ResponseRedirect
   */
  function redirect($to = null, $statusCode = 302)
  {
    return new ResponseRedirect($to, $statusCode);
  }
}
