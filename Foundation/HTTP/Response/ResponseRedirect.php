<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\App;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\Router\Routes;

/**
 * 重定向响应
 *
 * 参考 Laravel `Illuminate\Routing\Redirector` 的设计，提供链式、语义化的跳转能力：
 * - `to()`       跳转到任意路径或完整 URL
 * - `route()`    按命名路由生成 URL 跳转（复用 `Routes::url`）
 * - `away()`     跳转到外部域名（不强制同域/HTTPS）
 * - `secure()`   强制以 HTTPS 跳转到当前域名下的路径
 * - `back()`     回退到上一页（读取 `Referer`，缺失时回退到 fallback）
 * - `with()`     携带一次性数据（本框架无 Session，数据以 URL 查询参数形式附加到目标地址）
 * - `withFragment()` 附加 URL 片段（#anchor）
 *
 * 生命周期：跳转方法只「配置」目标与模式，`output()` 时才统一组装最终 URL
 * （合并 with 数据、片段、协议策略）并发送 `Location` 头与状态码，不输出响应主体。
 *
 * 与父类 `Response::redirect()` 的关系：父类 `redirect()` 是快捷方式（仅设 Location + 状态码）；
 * 本类在其之上提供路由生成、外部跳转、协议策略、数据携带等更完整的能力。
 */
class ResponseRedirect extends Response
{
  /**
   * 目标地址（已解析为绝对 URL 或外部地址）
   *
   * @var string|null
   */
  protected $redirectTo = null;
  /**
   * 是否为外部跳转（away），外部地址不强制同域与协议策略
   *
   * @var boolean
   */
  protected $isAway = false;
  /**
   * 是否强制 HTTPS（secure）
   *
   * @var boolean
   */
  protected $isSecure = false;
  /**
   * URL 片段（# 之后的部分，不含前导 #）
   *
   * @var string|null
   */
  protected $fragment = null;
  /**
   * 通过 with() 携带的一次性数据，输出时合并进查询字符串
   *
   * @var array
   */
  protected $withData = [];

  /**
   * 构建重定向响应
   *
   * 可直接传入初始目标地址与状态码；也可仅 `new self()` 后链式调用 `to()/route()/...`。
   *
   * @param string|null $to          初始目标地址（可选）
   * @param integer      $statusCode HTTP 状态码，默认 302（Found）
   */
  public function __construct($to = null, $statusCode = 302)
  {
    parent::__construct(null, $statusCode);
    if ($to !== null) {
      $this->to($to, $statusCode);
    }
  }

  /**
   * 跳转到指定地址
   *
   * 自动将相对路径（不含 `://`）补全为基于当前请求的绝对 URL；
   * 完整 URL（含 `://`）直接采用。可通过 `$headers` 附加额外响应头。
   *
   * @param string  $path       目标路径或完整 URL
   * @param integer $statusCode HTTP 状态码，默认 302
   * @param array   $headers    附加响应头 [key => value]
   * @return $this
   */
  public function to($path, $statusCode = 302, $headers = [])
  {
    $this->isAway = false;
    $this->isSecure = false;
    $this->responseStatusCode = $statusCode;
    // 完整 URL 直接采用，避免无谓的 App 上下文依赖
    $this->redirectTo = preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $path)
      ? $path
      : $this->resolveAbsoluteUrl($path);
    $this->applyHeaders($headers);

    return $this;
  }

  /**
   * 按命名路由生成 URL 并跳转
   *
   * 委托 `Routes::url()` 反向生成路由 URL，支持路由参数与域名。
   *
   * @param string  $name       路由名称
   * @param array   $parameters 路由参数
   * @param integer $statusCode HTTP 状态码，默认 302
   * @param array   $headers    附加响应头 [key => value]
   * @return $this
   */
  public function route($name, $parameters = [], $statusCode = 302, $headers = [])
  {
    $this->isAway = false;
    $this->isSecure = false;
    $this->responseStatusCode = $statusCode;
    $this->redirectTo = Routes::url($name, $parameters);
    $this->applyHeaders($headers);

    return $this;
  }

  /**
   * 跳转到外部地址（不强制同域/HTTPS）
   *
   * 用于跳转到第三方站点，不会对地址做协议或域名归一化。
   *
   * @param string  $path       外部完整 URL
   * @param integer $statusCode HTTP 状态码，默认 302
   * @param array   $headers    附加响应头 [key => value]
   * @return $this
   */
  public function away($path, $statusCode = 302, $headers = [])
  {
    $this->isAway = true;
    $this->isSecure = false;
    $this->responseStatusCode = $statusCode;
    $this->redirectTo = $path;
    $this->applyHeaders($headers);

    return $this;
  }

  /**
   * 以 HTTPS 跳转到当前域名下的路径
   *
   * @param string  $path       当前域名下的路径（不含协议）
   * @param integer $statusCode HTTP 状态码，默认 302
   * @param array   $headers    附加响应头 [key => value]
   * @return $this
   */
  public function secure($path, $statusCode = 302, $headers = [])
  {
    $this->isAway = false;
    $this->isSecure = true;
    $this->responseStatusCode = $statusCode;
    $this->redirectTo = $this->resolveAbsoluteUrl($path, true);
    $this->applyHeaders($headers);

    return $this;
  }

  /**
   * 回退到上一页
   *
   * 读取请求头 `Referer`；若缺失则回退到 `$fallback`（默认当前完整 URL）。
   *
   * @param integer    $statusCode HTTP 状态码，默认 302
   * @param string|null $fallback  回退兜底地址，默认当前请求完整 URL
   * @param array      $headers    附加响应头 [key => value]
   * @return $this
   */
  public function back($statusCode = 302, $fallback = null, $headers = [])
  {
    $this->isAway = true;
    $this->responseStatusCode = $statusCode;
    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    $this->redirectTo = $referer ?: ($fallback ?: URL::current());
    $this->applyHeaders($headers);

    return $this;
  }

  /**
   * 携带一次性数据
   *
   * 本框架无 Session 闪存，数据以 URL 查询参数形式附加到目标地址（前端可读、可再次透传）。
   * 支持 `with('key', 'value')` 单条，或 `with(['k1' => 'v1', 'k2' => 'v2'])` 批量。
   *
   * @param string|array $key   数据键名，或关联数组（批量）
   * @param mixed        $value 数据值（单条模式）
   * @return $this
   */
  public function with($key, $value = null)
  {
    if (is_array($key)) {
      $this->withData = array_merge($this->withData, $key);
    } else {
      $this->withData[$key] = $value;
    }

    return $this;
  }

  /**
   * 附加 URL 片段（锚点）
   *
   * 传入值可带或不带前导 `#`，统一在输出时拼为 `#fragment`。
   *
   * @param string $fragment 片段内容
   * @return $this
   */
  public function withFragment($fragment)
  {
    $this->fragment = ltrim($fragment, "#");

    return $this;
  }

  /**
   * 输出重定向响应
   *
   * 组装最终 URL（合并 with 数据、片段、协议策略），发送 `Location` 头与状态码，不输出主体。
   *
   * @return void
   */
  public function output()
  {
    $this->interactionOutput();

    $url = $this->buildFinalUrl();

    // Location 头优先置于响应头列表首部，便于后续统一遍历输出
    $this->responseHeaders = array_merge(
      [["key" => "Location", "value" => $url, "replace" => true]],
      $this->responseHeaders
    );

    foreach ($this->responseHeaders as $Header) {
      header($Header['key'] . ":" . $Header['value'], $Header['replace']);
    }
    http_response_code($this->responseStatusCode);
  }

  /**
   * 将相对路径解析为基于当前请求的绝对 URL
   *
   * @param string  $path   路径或完整 URL
   * @param boolean $secure 是否强制 HTTPS
   * @return string
   */
  protected function resolveAbsoluteUrl($path, $secure = false)
  {
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $path)) {
      // 已是完整 URL，直接采用（secure 仅对当前域名路径生效，这里不强行改写外部 URL）
      return $path;
    }

    $request = App::getInstance()->request();
    $host = $request->host();
    $protocol = $secure ? "https" : ($request->isSecure() ? "https" : "http");
    $pathName = $path !== "" && $path[0] !== "/" ? "/" . $path : $path;

    return URL::buildURL($host, $pathName, [], null, $protocol);
  }

  /**
   * 组装最终输出 URL（合并 with 数据与片段、应用协议策略）
   *
   * @return string
   */
  protected function buildFinalUrl()
  {
    $url = $this->redirectTo ?? "/";

    // 合并 with 数据到查询字符串
    if (!empty($this->withData)) {
      $parsed = parse_url($url);
      $query = [];
      if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query);
      }
      $query = array_merge($query, $this->withData);
      $parsed['query'] = http_build_query($query);

      $url = $this->unparseUrl($parsed);
    }

    // 附加片段
    if ($this->fragment !== null && $this->fragment !== "") {
      $url .= "#" . $this->fragment;
    }

    return $url;
  }

  /**
   * 将 parse_url 结果还原为字符串
   *
   * @param array $parts parse_url 结果
   * @return string
   */
  protected function unparseUrl($parts)
  {
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . "://" : "";
    $host = $parts['host'] ?? "";
    $port = isset($parts['port']) ? ":" . $parts['port'] : "";
    $user = isset($parts['user']) ? $parts['user'] : "";
    $pass = isset($parts['pass']) ? ":" . $parts['pass'] : "";
    $pass = ($user || $pass) ? $pass . "@" : "";
    $path = $parts['path'] ?? "";
    $query = isset($parts['query']) ? "?" . $parts['query'] : "";
    $fragment = isset($parts['fragment']) ? "#" . $parts['fragment'] : "";

    return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
  }

  /**
   * 批量附加响应头
   *
   * @param array $headers [key => value]
   * @return void
   */
  protected function applyHeaders($headers)
  {
    foreach ($headers as $key => $value) {
      $this->header($key, $value);
    }
  }
}
