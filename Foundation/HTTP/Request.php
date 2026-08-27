<?php

namespace kernel\Foundation\HTTP;

use kernel\Foundation\App;
use kernel\Foundation\HTTP\Request\RequestBody;
use kernel\Foundation\HTTP\Request\RequestData;
use kernel\Foundation\HTTP\Request\RequestHeader;
use kernel\Foundation\HTTP\Request\RequestParams;
use kernel\Foundation\HTTP\Request\RequestQuery;
use kernel\Foundation\Output;


class Request
{
  /**
   * 请求query
   *
   * @var RequestQuery
   */
  public $query = null;
  /**
   * 请求体
   *
   * @var RequestBody
   */
  public $body = null;
  /**
   * 请求头
   *
   * @var RequestHeader
   */
  public $header = null;
  /**
   * URI参数
   *
   * @var RequestParams
   */
  public $params = null;

  /**
   * 请求方法（私有存储，经 method() 读取/写入）
   *
   * @var string|null
   */
  private $method = null;
  /**
   * 请求URI（私有存储，经 uri() 读取/写入）
   *
   * @var string|null
   */
  private $uri = null;
  /**
   * 归一化后的请求头缓存（小写键名），首次访问时构建
   *
   * @var array|null
   */
  private $headers = null;

  public function __construct()
  {
    $this->query = new RequestQuery();
    $this->body = new RequestBody();
    $this->header = new RequestHeader();
    $this->params = new RequestParams();
  }
  /**
   * 获取/设置请求方法
   *
   * 无参调用时返回请求方法（首次调用从 $_SERVER['REQUEST_METHOD'] 延迟解析，dev 模式支持 _method 覆盖）；
   * 传参则写入请求方法并返回。
   *
   * @param string|null $value 请求方法（传入则写入）
   * @return string
   */
  public function method($value = null)
  {
    if ($value !== null) {
      $this->method = strtolower(addslashes($value));
    } else if ($this->method === null) {
      $method = $_SERVER['REQUEST_METHOD'] ?? "get";
      if (App::mode() === "development") {
        if ($this->query->has("_method")) $method = $this->query->get("_method");
        if ($this->params->has("_method")) $method = $this->params->get("_method");
      }
      if ($this->body->has("_method")) $method = $this->body->get("_method");
      $this->method = strtolower(addslashes($method));
    }
    return $this->method;
  }
  /**
   * 获取/设置请求URI
   *
   * 无参调用时返回请求URI（首次调用从 $_SERVER['REQUEST_URI'] 延迟解析，dev 模式支持 uri query 覆盖；
   * CLI 无 REQUEST_URI 时兜底 "/"）；传参则写入 URI 并返回。
   *
   * @param string|null $value 请求URI（传入则写入）
   * @return string
   */
  public function uri($value = null)
  {
    if ($value !== null) {
      $this->uri = $value;
    } else if ($this->uri === null) {
      if ($this->query->has("uri")) {
        $this->uri = $this->query->get("uri");
      } else {
        //* CLI 环境下不存在 REQUEST_URI，使用 "/" 兜底
        $requestURI = $_SERVER['REQUEST_URI'] ?? "/";
        $this->uri = substr($requestURI, 0, strpos($requestURI, "?") ?: strlen($requestURI));
      }
    }
    return $this->uri;
  }
  /**
   * 统一读取输入参数
   *
   * 按 params（路由参数）→ query → body 的顺序返回第一个存在的值；均不存在返回默认值。
   *
   * @param string $key 键名
   * @param mixed $default 默认值
   * @return mixed
   */
  public function input($key, $default = null)
  {
    foreach ([$this->params, $this->query, $this->body] as $source) {
      if ($source instanceof RequestData && $source->has($key)) return $source->get($key);
    }
    return $default;
  }
  /**
   * 是否存在指定输入
   *
   * @param string $key 键名
   * @return bool
   */
  public function hasInput($key)
  {
    foreach ([$this->params, $this->query, $this->body] as $source) {
      if ($source instanceof RequestData && $source->has($key)) return true;
    }
    return false;
  }
  /**
   * 合并全部输入参数
   *
   * params（路由参数）优先，依次被 query、body 覆盖；body 非数组时忽略。
   *
   * @return array
   */
  public function all()
  {
    $merged = [];
    foreach ([$this->params, $this->query, $this->body] as $source) {
      if ($source instanceof RequestData && is_array($source->some())) {
        $merged = array_merge($merged, $source->some());
      }
    }
    return $merged;
  }
  /**
   * 是否存在上传文件
   *
   * 不传键时判断是否有任意上传文件；传键时判断指定键是否有有效上传（error 为 UPLOAD_ERR_OK 或 UPLOAD_ERR_NO_FILE 视为无）。
   *
   * @param string|null $key 文件键名；null 表示任意上传
   * @return bool
   */
  public function hasFile($key = null)
  {
    if (empty($_FILES)) return false;
    if ($key === null) {
      foreach ($_FILES as $item) {
        if (self::valid($item)) return true;
      }
      return false;
    }
    if (!isset($_FILES[$key])) return false;
    return self::valid($_FILES[$key]);
  }
  /**
   * 获取上传文件
   *
   * 不传键时返回全部归一化后的上传文件映射（键名 => 单文件数组）；
   * 传键时返回归一化后的单文件数组，不存在或无效上传返回 null。
   * 归一化后的单文件结构：name/tmp_name/error/size/type/full_path。
   *
   * @param string|null $key 文件键名；null 返回全部
   * @return array|null
   */
  public function file($key = null)
  {
    if (empty($_FILES)) return $key === null ? [] : null;
    if ($key !== null) {
      if (!isset($_FILES[$key])) return null;
      return self::normalize($_FILES[$key]);
    }
    $files = [];
    foreach ($_FILES as $name => $item) {
      $files[$name] = self::normalize($item);
    }
    return $files;
  }
  /**
   * 归一化单个上传项，多文件字段（数组型）展平为单文件数组
   *
   * PHP 的多文件上传形如 $_FILES['name']['tmp_name'][0]…，这里统一展平为
   * 以 name 为键、单文件数组为值的映射，便于逐文件处理。
   *
   * @param array $item $_FILES 中的某一项
   * @return array|null 归一化后的单文件数组；无效结构返回 null
   */
  private static function normalize($item)
  {
    if (!is_array($item) || !isset($item['name'])) return null;
    if (!is_array($item['name'])) return $item;
    // 数组型多文件：字段本身为索引数组，各属性按相同下标对应
    $normalized = [];
    foreach ($item['name'] as $index => $name) {
      $normalized[$name] = [
        "name" => $name,
        "tmp_name" => $item['tmp_name'][$index] ?? null,
        "error" => $item['error'][$index] ?? UPLOAD_ERR_NO_FILE,
        "size" => $item['size'][$index] ?? 0,
        "type" => $item['type'][$index] ?? "",
        "full_path" => $item['full_path'][$index] ?? $name,
      ];
    }
    return $normalized;
  }
  /**
   * 判断上传项是否有效（存在文件且 error 为 UPLOAD_ERR_OK）
   *
   * @param array $item $_FILES 中的某一项
   * @return bool
   */
  private static function valid($item)
  {
    if (!is_array($item) || !isset($item['error'])) return false;
    if (is_array($item['error'])) {
      foreach ($item['error'] as $err) {
        if ($err === UPLOAD_ERR_OK) return true;
      }
      return false;
    }
    return $item['error'] === UPLOAD_ERR_OK;
  }
  /**
   * 是否请求方法等于指定方法
   *
   * @param string $method 方法名（大小写不敏感）
   * @return bool
   */
  public function isMethod($method)
  {
    return $this->method() === strtolower(trim($method));
  }
  /**
   * 是否为 GET 请求
   *
   * @return bool
   */
  public function isGet()
  {
    return $this->isMethod("get");
  }
  /**
   * 是否为 POST 请求
   *
   * @return bool
   */
  public function isPost()
  {
    return $this->isMethod("post");
  }
  /**
   * 是否为 PUT 请求
   *
   * @return bool
   */
  public function isPut()
  {
    return $this->isMethod("put");
  }
  /**
   * 是否为 DELETE 请求
   *
   * @return bool
   */
  public function isDelete()
  {
    return $this->isMethod("delete");
  }
  /**
   * 是否为 PATCH 请求
   *
   * @return bool
   */
  public function isPatch()
  {
    return $this->isMethod("patch");
  }
  /**
   * 获取规范化的请求路径
   *
   * 去除前导/尾随斜杠（非根路径时）、去除 query 串。
   *
   * @return string
   */
  public function path()
  {
    $uri = $this->uri();
    //* 移除 query 串
    $path = substr($uri, 0, strpos($uri, "?") ?: strlen($uri));
    //* 移除前导/尾随斜杠；根路径 "/" 保留
    if ($path === "/") return "/";
    return trim($path, "/");
  }
  /**
   * 获取请求路径的每一段
   *
   * @return array
   */
  public function segments()
  {
    $path = $this->path();
    if ($path === "" || $path === "/") return [];
    return explode("/", $path);
  }
  /**
   * 获取请求路径的第 index 段（从 0 开始）
   *
   * @param int $index 段下标
   * @return string|null
   */
  public function segment($index)
  {
    return $this->segments()[$index] ?? null;
  }
  /**
   * 判断请求路径是否匹配给定模式
   *
   * 模式支持 {param} 与 {param:regex} 占位符。纯判断、无副作用：
   * 匹配提取到的参数不写入 $this->params，如需使用可经第三参引用接收。
   *
   * @param string $pattern 路径模式，如 "links/{id}"
   * @param array|null &$params 匹配成功后以引用方式返回提取到的参数映射
   * @return bool
   */
  public function isPath($pattern, &$params = null)
  {
    $segments = $this->segments();
    $expected = explode("/", trim($pattern, "/"));

    if (count($segments) !== count($expected)) return false;

    $matched = [];
    foreach ($expected as $i => $part) {
      if (preg_match('/^\{(\w+)(?::([^}]+))?\}$/', $part, $m)) {
        $name = $m[1];
        $regex = isset($m[2]) && $m[2] !== "" ? $m[2] : "[^/]+";
        //* 用 # 作为定界符，避免正则中的 / 与定界符冲突
        if (!preg_match("#^" . $regex . "$#", $segments[$i])) return false;
        $matched[$name] = $segments[$i];
      } else if ($part !== $segments[$i]) {
        return false;
      }
    }

    if ($matched) $params = $matched;
    return true;
  }
  /**
   * 获取 User-Agent 请求头
   *
   * @return string|null
   */
  public function userAgent()
  {
    return $this->caseInsensitiveHeader("User-Agent");
  }
  /**
   * 获取 Referer 请求头
   *
   * @return string|null
   */
  public function referrer()
  {
    return $this->caseInsensitiveHeader("Referer");
  }
  /**
   * 获取请求协议
   *
   * 默认按 $_SERVER['HTTPS'] 判断；当配置了可信代理时才回退 HTTP_X_FORWARDED_PROTO。
   *
   * @return string "http" | "https"
   */
  public function scheme()
  {
    $https = $_SERVER['HTTPS'] ?? null;
    if ($https && $https !== "off" && strtolower($https) !== "off") return "https";
    if (getenv("TRUSTED_PROXY") || getenv("trusted_proxy")) {
      $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
      if ($proto && strtolower(trim($proto)) === "https") return "https";
    }
    return "http";
  }
  /**
   * 是否为 HTTPS 请求
   *
   * @return bool
   */
  public function isSecure()
  {
    return $this->scheme() === "https";
  }
  /**
   * 获取请求主机名（Host 头优先，缺省取 SERVER_NAME）
   *
   * @return string|null
   */
  public function host()
  {
    return $this->caseInsensitiveHeader("Host") ?? ($_SERVER['SERVER_NAME'] ?? null);
  }
  /**
   * 获取完整请求 URL（scheme://host/path，不含 query）
   *
   * @return string|null
   */
  public function fullUrl()
  {
    $host = $this->host();
    if ($host === null) return null;
    $path = $this->uri();
    return $this->scheme() . "://" . $host . $path;
  }
  /**
   * 获取 Cookie 值
   *
   * @param string $key 键名
   * @param mixed $default 默认值
   * @return mixed
   */
  public function cookie($key, $default = null)
  {
    return $_COOKIE[$key] ?? $default;
  }
  /**
   * 是否为 CLI 环境
   *
   * @return bool
   */
  public function isCli()
  {
    return \PHP_SAPI === "cli";
  }
  /**
   * 获取用户IP地址
   *
   * 默认以 REMOTE_ADDR（TCP 连接的远端地址，不可伪造）为准。
   * 仅当进程配置了可信代理时才回退到 X-Forwarded-For / Client-IP，否则这些头可被客户端任意伪造。
   * 所有候选值都经 filter_var 校验，多值（逗号分隔）取第一个有效 IP。
   *
   * @return string|null IP地址
   */
  public static function ip()
  {
    $ip = self::validatedIp($_SERVER['REMOTE_ADDR'] ?? null);
    if ($ip) return $ip;

    // 仅信任可信代理注入的转发头；非可信代理下这些头可被伪造，直接忽略
    if (getenv("TRUSTED_PROXY") || getenv("trusted_proxy")) {
      $candidates = [$_SERVER['HTTP_X_FORWARDED_FOR'] ?? null, $_SERVER['HTTP_CLIENT_IP'] ?? null];
      foreach ($candidates as $candidate) {
        if (!$candidate) continue;
        // X-Forwarded-For 可能是逗号分隔的链，取最左侧（发起方）的有效 IP
        $parts = explode(",", $candidate);
        foreach ($parts as $part) {
          $validated = self::validatedIp(trim($part));
          if ($validated) return $validated;
        }
      }
    }

    return null;
  }
  /**
   * 校验并规范化IP地址
   *
   * @param mixed $ip 待校验的IP
   * @return string|null 合法则返回IP，否则返回null
   */
  private static function validatedIp($ip)
  {
    if (!is_string($ip) || $ip === "") return null;
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
      return $ip;
    }
    return null;
  }
  /**
   * 根据请求头推断客户端期望的输出内容格式，用于响应未显式调用 json()/text()/view() 等时的兜底判断
   *
   * 推断顺序：
   * 1. 请求体 Content-Type：application/json（含 +json）-> json；application/xml、text/xml、+xml -> xml；text/html -> html；text/plain -> text
   * 2. Accept 请求头（逗号分隔逐项判定）：application/json -> json；application/xml、text/xml -> xml；text/html -> html；text/plain -> text；通配 MIME 跳过
   *
   * 判断完全基于请求头，不依赖 ajax() 标志。controller 已显式设置输出类型时优先使用控制器返回值，不会走到这里。
   *
   * @return string|null 期望的输出类型（json/xml/html/text），无法推断时返回 null
   */
  public function preferredOutputType()
  {
    // 1. 请求体 Content-Type（大小写不敏感）
    $contentType = $this->caseInsensitiveHeader("Content-Type");
    if ($contentType) {
      $contentType = strtolower(trim(explode(";", $contentType)[0]));
      if ($this->matchesMime($contentType, "json")) return "json";
      if ($this->matchesMime($contentType, "xml")) return "xml";
      if ($this->matchesMime($contentType, "html")) return "html";
      if ($this->matchesMime($contentType, "text")) return "text";
    }

    // 2. Accept 请求头
    $accept = $this->caseInsensitiveHeader("Accept");
    if ($accept) {
      // Accept 可能为逗号分隔的多个值，逐个判定
      foreach (explode(",", $accept) as $candidate) {
        $candidate = strtolower(trim(explode(";", $candidate)[0]));
        if ($candidate === "*/*" || $candidate === "") continue;
        if ($this->matchesMime($candidate, "json")) return "json";
        if ($this->matchesMime($candidate, "xml")) return "xml";
        if ($this->matchesMime($candidate, "html")) return "html";
        if ($this->matchesMime($candidate, "text")) return "text";
      }
    }

    return null;
  }
  /**
   * 判定 MIME 类型是否属于给定格式族。
   *
   * 精确匹配 subtype 边界，避免把 application/json-patch+json、application/xml-dtd 等误判为目标格式。
   *
   * @param string $mime 已小写的 MIME 类型（不含参数）
   * @param string $family 格式族（json/xml/html/text）
   * @return bool
   */
  private function matchesMime($mime, $family)
  {
    $map = [
      "json" => ["json"],
      "xml" => ["xml", "xml-dtd", "xhtml+xml"],
      "html" => ["html", "xhtml+xml"],
      "text" => ["plain"],
    ];
    if (!isset($map[$family])) return false;
    $parts = explode("/", $mime);
    $subtype = end($parts);
    foreach ($map[$family] as $needle) {
      // subtype 精确等于，或形如 application/json-patch+json（含 +json 后缀）
      if ($subtype === $needle || substr($subtype, - (strlen($needle) + 1)) === "+" . $needle) {
        return true;
      }
    }
    return false;
  }
  /**
   * 大小写不敏感地读取请求头
   *
   * 首次调用时把 $_SERVER 中 HTTP 前缀与 CONTENT 前缀的键以及 getallheaders 归一并缓存，
   * 后续读取 O(1)。缓存键统一为小写（下划线转连字符）。
   *
   * @param string $key 头名称
   * @return string|null
   */
  private function caseInsensitiveHeader($key)
  {
    if ($this->headers === null) $this->headers = $this->buildHeaders();
    $needle = strtolower($key);
    return $this->headers[$needle] ?? null;
  }
  /**
   * 构建归一化的请求头映射（小写键名，下划线转连字符）
   *
   * @return array
   */
  private function buildHeaders()
  {
    $headers = [];
    // $_SERVER 中请求头通常以 HTTP_ 前缀命名，但 Content-Type/Content-Length 等是例外，
    // 直接以 CONTENT_ 前缀出现（不带 HTTP_）。两种形态都纳入扫描。
    foreach ($_SERVER as $name => $value) {
      $headerName = $name;
      if (substr($name, 0, 5) === "HTTP_") {
        $headerName = substr($name, 5);
      } else if (substr($name, 0, 8) === "CONTENT_") {
        $headerName = $name;
      } else {
        continue;
      }
      $headers[strtolower(str_replace("_", "-", $headerName))] = $value;
    }
    // getallheaders 场景下可能不在 $_SERVER，回退到精确匹配补充
    if (\function_exists("getallheaders")) {
      foreach (\getallheaders() as $name => $value) {
        $headers[strtolower($name)] = $value;
      }
    }
    return $headers;
  }
  /**
   * 是否是ASYNC请求。该类型请求是服务器自己通过CURL向自己发起的HTTP请求，头部会带有x-async标识
   *
   * @return bool
   */
  public function async()
  {
    if (App::mode() === "development") {
      if ($this->query->has("x-async")) return true;
      if ($this->body->has("x-async")) return true;
      if ($this->params->has("x-async")) return true;
    }

    if ($this->header->has("X-Async") || $this->header->has("x-async")) return true;

    return false;
  }
}
