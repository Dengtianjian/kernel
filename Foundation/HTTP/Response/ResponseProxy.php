<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\Config;
use kernel\Foundation\HTTP\Response;

/**
 * 远程资源代理响应（服务端中转输出）
 *
 * 用途：把**浏览器自己取不了**的远程资源（如 `ftp://` / `ftps://`）由服务端取回后流式转发给客户端，
 * 这样即使不做 302 跳转也能在浏览器内预览/下载 —— 现代浏览器（Chrome / Edge / Firefox）都已移除
 * FTP 支持，302 到 `ftp://` 只会失败或交给外部客户端。
 *
 * 与 {@see ResponseFile} 的区别：后者只接受**本地绝对路径**；本类接受**远程 URL**，边读边发，
 * 不会把整份资源读进内存。本类不需要 Request（既不处理 Range，也不读请求参数）。
 *
 * 安全与限制（重要）：
 * - 只应传入**由存储磁盘产出**的 URL（{@see \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage::url()}）；
 *   切勿把用户输入直接传进来，否则等于给站点开了一个 SSRF 跳板；
 * - 协议白名单见 {@see canProxy()}（默认 `ftp` / `ftps`，可用配置扩展）；
 * - 超时与体积上限见 {@see proxyTimeout()} / {@see proxyMaxSize()}（配置 `storage.*` 可覆盖）；
 * - 不做 Range（响应 `Accept-Ranges: none`）：代理出来的资源不支持拖动进度/断点续传；
 * - `text/html`、`image/svg+xml`、XML、JS 等**可执行类型强制转为附件下载**，
 *   避免远程（可能是用户上传的）内容在**本站源**下内联执行造成 XSS。
 *
 * 配置键（`storage.*`，未配置时用下方常量默认值）：
 * - `remoteProxyProtocols` 允许代理的协议列表（数组，或逗号分隔字符串），默认 `["ftp","ftps"]`
 * - `remoteProxyTimeout`   连接/读取超时（秒），默认 10
 * - `remoteProxyMaxSize`   单次最大转发体积（字节），默认 20MB；远端已声明长度时先返回 413，长度未知时截断
 */
class ResponseProxy extends Response
{
  /**
   * 默认连接/读取超时（秒）
   */
  const DEFAULT_TIMEOUT = 10;
  /**
   * 默认单次最大转发体积（20MB，字节）
   */
  const DEFAULT_MAX_SIZE = 20971520;
  /**
   * 用于 MIME 探测的采样字节数
   */
  const SAMPLE_SIZE = 8192;
  /**
   * 单次读取块大小（字节）
   */
  const CHUNK_SIZE = 8192;
  /**
   * 默认允许代理的协议
   */
  const DEFAULT_PROTOCOLS = ["ftp", "ftps"];
  /**
   * 内联输出会带来 XSS 风险、因此强制转为附件下载的 MIME 类型
   */
  const UNSAFE_INLINE_TYPES = [
    "text/html",
    "application/xhtml+xml",
    "image/svg+xml",
    "text/xml",
    "application/xml",
    "application/javascript",
    "text/javascript",
    "application/x-shockwave-flash",
  ];

  /**
   * 远程资源地址
   *
   * @var string
   */
  protected $url = null;
  /**
   * 输出给客户端时的文件名
   *
   * @var string
   */
  protected $fileName = null;
  /**
   * HTTP 缓存控制值
   *
   * @var string
   */
  protected $cacheControl = "no-cache";

  /**
   * 构造代理响应
   *
   * @param string $url 远程资源地址（**必须来自存储磁盘**，见类注释的安全说明）
   * @param string|null $downloadFileName 输出文件名；不传时取 URL 路径的 basename
   * @param string $cacheControl HTTP 缓存控制值
   * @return void
   */
  public function __construct($url, $downloadFileName = null, $cacheControl = "no-cache")
  {
    parent::__construct();

    $this->url = $url;
    $this->fileName = $downloadFileName ?: basename(parse_url((string) $url, PHP_URL_PATH) ?: "");
    $this->cacheControl = $cacheControl;
  }

  /**
   * 判断该地址能否由服务端代理取回
   *
   * 两个条件同时满足才返回 true：
   * 1. 协议在允许列表内（`storage/remoteProxyProtocols`，默认 ftp / ftps）；
   * 2. 当前 PHP 已注册该协议的流包装器（{@see stream_get_wrappers()}）—— 没注册就取不回来，
   *    此时应让调用方回退到 302 跳转等其它方式，而不是发出注定失败的代理。
   *
   * @param string|null $url 待判断的地址
   * @return boolean 可代理返回 true
   */
  public static function canProxy($url)
  {
    $scheme = strtolower((string) parse_url((string) $url, PHP_URL_SCHEME));
    if ($scheme === "") {
      return false;
    }
    if (!in_array($scheme, self::proxyProtocols(), true)) {
      return false;
    }

    return in_array($scheme, stream_get_wrappers(), true);
  }

  /**
   * 输出远程资源（流式转发）
   *
   * 顺序：打开远程流（带超时）→ 远端声明长度超限则 413 → 采样首块探测 MIME → 发头 →
   * 先输出采样块再循环分块输出（客户端断开或达到体积上限即停止）。
   *
   * @return void
   */
  public function output()
  {
    $maxSize = self::proxyMaxSize();
    $context = stream_context_create([
      "http" => ["timeout" => self::proxyTimeout(), "max_redirects" => 3],
      "ftp" => ["timeout" => self::proxyTimeout()],
    ]);

    $handle = @fopen($this->url, "rb", false, $context);
    if ($handle === false) {
      //* 远端取不回来（不存在/无权限/超时）：502，交由上层或网关提示
      header("HTTP/1.1 502 Bad Gateway");
      return;
    }

    //* 远端已声明长度且超过上限：不开始传输，直接拒绝
    $remoteSize = self::remoteContentLength(isset($http_response_header) ? $http_response_header : null);
    if ($remoteSize !== null && $remoteSize > $maxSize) {
      fclose($handle);
      header("HTTP/1.1 413 Payload Too Large");
      return;
    }

    //* 采样首块用于 MIME 探测（以内容为准，扩展名不可靠）
    $sample = fread($handle, self::SAMPLE_SIZE);
    if ($sample === false) {
      fclose($handle);
      header("HTTP/1.1 502 Bad Gateway");
      return;
    }

    $mimeType = self::detectMimeType($sample);
    $inline = !in_array($mimeType, self::UNSAFE_INLINE_TYPES, true);

    header("Accept-Ranges: none");
    header("Content-Type: " . $mimeType);
    header("X-Content-Type-Options: nosniff");
    header("Content-Disposition: " . ($inline ? "inline" : "attachment") . '; filename="' . rawurlencode($this->fileName) . '"');
    header("Cache-Control: " . $this->cacheControl);
    if ($remoteSize !== null) {
      header("Content-Length: " . $remoteSize);
    }

    //* 先输出采样块（采样仍按 SAMPLE_SIZE 读取以保证 MIME 识别，但输出要受上限约束），再继续分块转发
    $sent = 0;
    if ($sample !== "") {
      $firstChunk = strlen($sample) > $maxSize ? substr($sample, 0, $maxSize) : $sample;
      echo $firstChunk;
      $sent = strlen($firstChunk);
      flush();
    }

    while (!feof($handle)) {
      //* 长度未知时的截断保护（已声明长度的情况在发头前就拒绝了）
      if ($sent >= $maxSize) break;
      //* 客户端断开即停止，避免继续空转到远端超时
      if (connection_aborted()) break;

      $chunk = fread($handle, self::CHUNK_SIZE);
      if ($chunk === false || $chunk === "") break;

      echo $chunk;
      $sent += strlen($chunk);
      flush();
    }

    fclose($handle);
  }

  /**
   * 解析允许代理的协议列表
   *
   * 支持数组与逗号分隔字符串两种配置形态；非法/为空时回落到 {@see DEFAULT_PROTOCOLS}。
   *
   * @return string[] 小写协议名数组
   */
  protected static function proxyProtocols()
  {
    $value = self::config("remoteProxyProtocols", self::DEFAULT_PROTOCOLS);
    if (is_string($value)) {
      $value = explode(",", $value);
    }
    if (!is_array($value)) {
      return self::DEFAULT_PROTOCOLS;
    }

    $protocols = array_values(array_filter(array_map(function ($item) {
      return strtolower(trim((string) $item));
    }, $value)));

    return $protocols ? $protocols : self::DEFAULT_PROTOCOLS;
  }

  /**
   * 连接/读取超时（秒，配置 storage/remoteProxyTimeout）
   *
   * @return int
   */
  protected static function proxyTimeout()
  {
    $timeout = (int) self::config("remoteProxyTimeout", self::DEFAULT_TIMEOUT);

    return $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
  }

  /**
   * 单次最大转发体积（字节，配置 storage/remoteProxyMaxSize）
   *
   * 远端声明长度时会先以 413 拒绝；长度未知时按该值截断。
   *
   * @return int
   */
  protected static function proxyMaxSize()
  {
    $size = (int) self::config("remoteProxyMaxSize", self::DEFAULT_MAX_SIZE);

    return $size > 0 ? $size : self::DEFAULT_MAX_SIZE;
  }

  /**
   * 读取 storage.* 配置（带默认值）
   *
   * 配置未加载或读取失败时返回默认值，避免因为配置层异常导致响应直接失败。
   *
   * @param string $key 配置键（不含 storage/ 前缀）
   * @param mixed $default 默认值
   * @return mixed
   */
  protected static function config($key, $default)
  {
    try {
      return Config::get("storage/" . $key, $default);
    } catch (\Throwable $e) {
      return $default;
    }
  }

  /**
   * 从远端响应头里取内容长度
   *
   * 只有 http(s) 流包装器会填充 `$http_response_header`；ftp 等协议取不到，返回 null，
   * 此时按「长度未知」分块转发（不发送 Content-Length）。
   *
   * @param array|null $headers 远端响应头（`$http_response_header`）
   * @return int|null 字节数；取不到返回 null
   */
  protected static function remoteContentLength($headers)
  {
    if (!is_array($headers)) {
      return null;
    }

    foreach (array_reverse($headers) as $line) {
      if (stripos($line, "content-length:") === 0) {
        return (int) trim(substr($line, strlen("content-length:")));
      }
    }

    return null;
  }

  /**
   * 依据内容采样探测 MIME 类型
   *
   * 以内容为准（`finfo`），探测不出时退化为通用二进制流（浏览器会转为下载）。
   *
   * @param string $sample 内容采样（首块）
   * @return string MIME 类型
   */
  protected static function detectMimeType($sample)
  {
    if ($sample !== "" && function_exists("finfo_open")) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo !== false) {
        $mimeType = finfo_buffer($finfo, $sample);
        finfo_close($finfo);
        if (is_string($mimeType) && $mimeType !== "" && $mimeType !== "application/octet-stream") {
          return strtolower(trim(explode(";", $mimeType)[0]));
        }
      }
    }

    return "application/octet-stream";
  }
}
