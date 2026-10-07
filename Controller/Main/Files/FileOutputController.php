<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Response\ResponseProxy;
use kernel\Foundation\HTTP\URL;

/**
 * 文件输出控制器（预览 / 下载的公共实现）
 *
 * {@see PrewiewFileController} 与 {@see DownloadFileController} 的流程**除"输出方式"外完全相同**，
 * 曾各自复制一份（前 40 余行逐字相同）。本类作为控制器承载那套公共流程，两者继承它、只声明自己的输出方式：
 *
 * - 覆写 {@see $download} 为 `true` 即"下载"（本地 attachment、代理也强制 attachment），默认"预览"（inline）；
 * - 子类实现唯一的端点方法 `data($fileKey)`，调用本类的 {@see output()}。
 *
 * 公共流程：
 * 1. 鉴权：{@see Storage::authorizeOperation()}（失败直接回 {@see Storage::return()}）；
 * 2. 取文件记录：开启数据存储时查模型，否则走 {@see Storage::get()}；
 * 3. 404 兜底；
 * 4. 过滤掉签名相关 query 参数（{@see SIGN_QUERY_PARAMS}），其余留给目标地址；
 * 5. 按记录里的 `disk` 取磁盘、取访问地址（{@see \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage::url()}）；
 * 6. **远程地址**：能被服务端代理的协议（如 `ftp://`，见 {@see ResponseProxy::canProxy()}）→ 代理取回后转发；
 *    其余（http/https 等）→ 补全协议相对地址、并入 query 参数后 302 跳转（省服务端带宽）；
 * 7. **本地路径**：预览 → {@see \kernel\Foundation\HTTP\Response\ResponseFile}（inline，带 `max-age=43200`）；
 *    下载 → {@see \kernel\Foundation\HTTP\Response\ResponseDownload}（attachment）。
 *
 * 说明：本类**不声明 `data()`**（端点方法由子类给出，也就不会被误当成可路由端点），
 * 只提供公共流程；因而是"控制器"而非纯工具类 —— 逻辑与控制器同处一层，可直接使用
 * `$this->query()` / `$this->fail()` / `$this->request` 等受保护成员。
 *
 * @package kernel\Controller\Main\Files
 */
class FileOutputController extends Controller
{
  /**
   * 签名相关的 query 参数：不应透传给目标地址
   */
  const SIGN_QUERY_PARAMS = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];

  /**
   * 是否为下载模式
   *
   * `false` = 预览（本地 inline；代理按 MIME 决定 inline/attachment）；
   * `true` = 下载（本地 attachment；代理**强制** attachment）。
   *
   * @var boolean
   */
  protected $download = false;

  /**
   * 输出指定文件
   *
   * 由子类的 `data()` 调用；流程见类注释。失败一律通过 {@see Controller::fail()} 返回错误响应。
   *
   * @param string|null $fileKey 文件键
   * @return mixed 文件 / 代理 / 跳转响应，或错误响应
   */
  protected function output($fileKey = null)
  {
    if (!Storage::authorizeOperation($fileKey, "read")) return Storage::return();

    if (Storage::dataSave()) {
      $file = Storage::model()->where("key", $fileKey)->first();
    } else {
      $file = Storage::get($fileKey);
      if (Storage::isError()) return Storage::return();
    }

    if (!$file) {
      return $this->fail(404, 404, "文件不存在");
    }

    $urlParams = [];
    foreach ($this->query() as $key => $value) {
      if (!in_array($key, self::SIGN_QUERY_PARAMS, true)) {
        $urlParams[$key] = $value;
      }
    }

    $disk = Storage::disk($file['disk']);
    if (!$disk) {
      return $this->fail(500, 500, "抱歉，当前文件无法" . $this->actionName(), "文件所属存储平台未实例化");
    }

    $url = $disk->url($fileKey);
    if (!$url) return $this->fail(500, 500, $this->actionName() . "文件失败", "获取到的文件的URL为空");

    //* 带 URL 前缀的绝对地址（http/https/ftp/ftps/sftp/oss://… 任意协议，含协议相对 //host）
    if (URL::isAbsoluteURL($url)) {
      //* 浏览器自己取不了的协议（如 ftp，现代浏览器已移除 FTP 支持）→ 由服务端取回后流式转发
      if (ResponseProxy::canProxy($url)) {
        return $this->response->proxy($url, $file['source_file_name'], "no-cache", $this->download);
      }

      //* 其余（http/https 等浏览器能直接取的）→ 302 跳转，省掉服务端中转带宽
      //* 协议相对地址（//host/path）补全为当前请求协议，否则会被 ResponseRedirect 当成同域相对路径解析
      if (strpos($url, "//") === 0) {
        $url = ($this->request->isSecure() ? "https:" : "http:") . $url;
      }

      $fileURL = new URL($url);
      $fileURL->queryParam($urlParams);

      return $this->response->redirect($fileURL->toString(), 302);
    }

    //* 本地路径：直接输出文件实体
    if (!file_exists($url)) {
      return $this->fail(500, 500, "文件不存在", "文件实体不存在");
    }

    return $this->download
      ? $this->response->download($url, $file['source_file_name'])
      : $this->response->file($url, $file['source_file_name'], null, "max-age=43200");
  }

  /**
   * 动作名称（用于失败文案：「预览」/「下载」）
   *
   * @return string
   */
  protected function actionName()
  {
    return $this->download ? "下载" : "预览";
  }
}
