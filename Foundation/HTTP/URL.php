<?php

namespace kernel\Foundation\HTTP;

/**
 * URL 统一资源定位器类
 */
class URL
{
  protected $URL = null;
  public $host = null;
  public $origin = null;
  public $port = null;
  public $user = null;
  public $password = null;
  public $pathName = null;
  public $protocol = null;
  public $queryString = null;
  public $queryParams = [];
  public $fragment = null;
  /**
   * 构建URL类实例
   *
   * @param string $URL URL
   */
  public function __construct($URL = null)
  {
    $this->URL = $URL;

    $ParsedURL = self::parseURL($URL);
    $this->protocol = $ParsedURL['protocol'];
    $this->host = $ParsedURL['host'];
    $this->port = $ParsedURL['port'];
    $this->user = $ParsedURL['user'];
    $this->password = $ParsedURL['password'];
    $this->pathName = $ParsedURL['pathName'];
    $this->queryString = $ParsedURL['queryString'];
    $this->fragment = $ParsedURL['fragment'];
    $this->origin = $ParsedURL['origin'];
    $this->queryParams = $ParsedURL['queryParams'];
  }

  static function baseURL()
  {
    $url = "";

    if (array_key_exists("REQUEST_SCHEME", $_SERVER)) {
      if (array_key_exists("HTTPS", $_SERVER) && $_SERVER['HTTPS'] === 'on') {
        $url .= "https://";
      } else {
        $url .= "http://";
      }

      // HTTP_HOST 缺失时返回已带 scheme 前缀的空 host，交由调用方判断
      if (array_key_exists("HTTP_HOST", $_SERVER) && $_SERVER['HTTP_HOST']) {
        $url .= $_SERVER['HTTP_HOST'];
      } else {
        $url = "";
      }
    }

    return $url;
  }
  /**
   * 解析URL
   *
   * @param string $URL URL地址
   * @return array
   */
  static function parseURL($URL)
  {
    $ParsedURL = parse_url((string)$URL) ?: [];

    // 各组成部分缺失时取 null，避免直接下标访问触发 Undefined array key 警告
    $protocol = $ParsedURL['scheme'] ?? null;
    $host = $ParsedURL['host'] ?? null;
    $port = $ParsedURL['port'] ?? null;

    // origin 按标准语义计算为 scheme://host(:port)
    $origin = null;
    if ($protocol && $host) {
      $origin = "{$protocol}://{$host}";
      if ($port) {
        $origin .= ":{$port}";
      }
    }

    return [
      "protocol" => $protocol,
      "host" => $host,
      "port" => $port,
      "user" => $ParsedURL['user'] ?? null,
      "password" => $ParsedURL['pass'] ?? null,
      "pathName" => $ParsedURL['path'] ?? null,
      "queryString" => $ParsedURL['query'] ?? null,
      "fragment" => $ParsedURL['fragment'] ?? null,
      "origin" => $origin,
      "queryParams" => self::parseQueryString($ParsedURL['query'] ?? null)
    ];
  }
  /**
   * 解析请求字符串
   *
   * @param string $queryString 请求字符串
   * @return array
   */
  static function parseQueryString($queryString)
  {
    if (!$queryString) return [];

    $StringList = explode("&", $queryString);
    if (!$StringList) return [];

    $QueryParams = [];
    foreach ($StringList as $item) {
      // 无 "=" 时（如 "?a&b"）value 缺省为空串，避免 list 解构 Undefined array key
      list($key, $value) = array_pad(explode("=", $item), 2, "");
      $QueryParams[rawurldecode(urldecode($key))] = rawurldecode(urldecode($value));
    }

    return $QueryParams;
  }
  /**
   * 构建请求字符串
   *
   * @param array $queryParams 请求字符串参数
   * @param boolean $encode 是否对键值进行编码
   * @return string 构建后的请求字符串
   */
  static function buildQuery($queryParams, $encode = true)
  {
    $queryString = "";
    if ($queryParams) {
      $queryStrings = [];
      foreach ($queryParams as $key => $value) {
        if (is_numeric($key)) {
          $key = $value;
          $value = "";
        }
        if ($encode) {
          $key = rawurlencode($key);
          $value = rawurlencode($value);
        }
        array_push($queryStrings, "{$key}={$value}");
      }
      $queryString = implode("&", $queryStrings);
    }
    return $queryString;
  }
  /**
   * 构建URL
   *
   * @param string $host 主机信息
   * @param string $pathName 路径
   * @param array $queryParams 请求参数
   * @param string $fragment hash片段
   * @param string $protocol 请求协议
   * @param int $port 端口
   * @param string $user 用户名
   * @param string $password 密码
   * @return string 构建后的URL
   */
  static function buildURL(
    $host = "",
    $pathName = "",
    $queryParams = [],
    $fragment = null,
    $protocol = "https",
    $port = null,
    $user = null,
    $password = null
  ) {

    $protocol = "$protocol://";
    $pathName = $pathName ? "/{$pathName}" : "";
    $port = $port ? ":{$port}" : "";
    $fragment = $fragment ? "#{$fragment}" : "";
    $auth = "";
    // 仅用户名存在时才拼 auth 段；密码仅作为用户名的补充（user:pass@）
    if ($user) {
      $auth = $password ? "{$user}:{$password}@" : "{$user}@";
    }
    $queryString = "";
    if ($queryParams) {
      $queryString = self::buildQuery($queryParams);
      if ($queryString) {
        $queryString = "?{$queryString}";
      }
    }

    return implode("", [
      $protocol,
      $auth,
      $host,
      $port,
      $pathName,
      $queryString,
      $fragment
    ]);
  }
  /**
   * 组合成一个路径名称
   *
   * @param string[] ...$paths 路径元素
   * @return string
   */
  static function combinedPathName(...$paths)
  {
    // 过滤空段，并去掉每段首尾的斜杠/反斜杠，再以单斜杠拼接，天然避免段间重复斜杠
    $path = implode("/", array_map(function ($item) {
      return trim((string)$item, "/\\");
    }, array_filter($paths, function ($item) {
      return !empty(trim((string)$item));
    })));

    // 仅合并非协议后的连续斜杠，避免误伤 "https://host" 中的合法双斜杠
    $path = preg_replace('#(?<!:)//+#', '/', $path);

    return $path;
  }
  /**
   * 字符串化URL所有参数，也就是把所有参数组合成一个URL
   *
   * @return string
   */
  public function toString()
  {
    // pathName 规范化：去掉首尾斜杠，避免与 buildURL 自动加的前导 "/" 重复（解析出的 pathName 通常带 "/"）
    $pathName = trim((string)$this->pathName, "/\\");
    return self::buildURL($this->host, $pathName, $this->queryParams, $this->fragment, $this->protocol, $this->port, $this->user, $this->password);
  }
  public function __toString()
  {
    return $this->toString();
  }
  /**
   * 设置协议
   *
   * @param string $protocol 协议
   * @return this
   */
  public function setProtocol($protocol)
  {
    $this->protocol = $protocol;
    return $this;
  }
  /**
   * 设置主机
   *
   * @param string $host 主机
   * @return this
   */
  public function setHost($host)
  {
    $this->host = $host;
    return $this;
  }
  /**
   * 设置路径（保留已有 query/fragment）
   *
   * @param string $pathName 路径
   * @return this
   */
  public function setPath($pathName)
  {
    $this->pathName = $pathName;
    return $this;
  }
  /**
   * 设置端口
   *
   * @param int|null $port 端口
   * @return this
   */
  public function setPort($port)
  {
    $this->port = $port;
    return $this;
  }
  /**
   * 设置hash片段
   *
   * @param string|null $fragment hash片段
   * @return this
   */
  public function setFragment($fragment)
  {
    $this->fragment = $fragment;
    return $this;
  }
  /**
   * 是否 HTTPS 协议
   *
   * @return boolean
   */
  public function isHttps()
  {
    return $this->protocol === "https";
  }
  /**
   * 获取去掉 www. 前缀的域名
   *
   * @return string|null
   */
  public function getDomain()
  {
    if (!$this->host) return null;
    return preg_replace('/^www\./i', '', $this->host);
  }
  /**
   * 获取站点根地址（protocol://host，不含路径）
   *
   * @return string|null
   */
  public function getBase()
  {
    if (!$this->protocol || !$this->host) return null;
    $base = "{$this->protocol}://{$this->host}";
    if ($this->port) {
      $base .= ":{$this->port}";
    }
    return $base;
  }
  /**
   * 设置请求参数
   *
   * @param string|array $value 参数值
   * @param string $key 参数名
   * @return this
   */
  public function queryParam($value, $key = null)
  {
    if (is_array($value)) {
      // 批量：以传入数组的键值对为参数逐一写入；数值键退化为裸参数
      foreach ($value as $paramKey => $paramValue) {
        if (is_int($paramKey)) {
          $this->queryParams[$paramValue] = "";
        } else {
          $this->queryParam($paramValue, $paramKey);
        }
      }
    } else if ($value || (!$value && $key)) {
      if (!$key) {
        // 仅传值：把值当作裸参数键（解码后）写入
        $key = rawurldecode($value);
        $value = "";
      }
      $this->queryParams[$key] = $value;
    }

    return $this;
  }
  /**
   * 获取请求参数
   *
   * @param string $key 参数名
   * @param mixed $default 缺省默认值
   * @return mixed
   */
  public function getQueryParam($key, $default = null)
  {
    return array_key_exists($key, $this->queryParams) ? $this->queryParams[$key] : $default;
  }
  /**
   * 是否存在请求参数
   *
   * @param string $key 参数名
   * @return boolean
   */
  public function hasQueryParam($key)
  {
    return array_key_exists($key, $this->queryParams);
  }
  /**
   * 移除请求参数
   *
   * @param string $key 参数名
   * @return this
   */
  public function removeQueryParam($key)
  {
    if (array_key_exists($key, $this->queryParams)) {
      unset($this->queryParams[$key]);
    }
    return $this;
  }
  /**
   * 清空请求参数
   *
   * @return this
   */
  public function clearQueryParams()
  {
    $this->queryParams = [];
    return $this;
  }
  /**
   * 获取当前请求完整 URL
   *
   * 读取 $_SERVER 推导当前请求的完整地址（含 query 与 fragment）
   *
   * @return string 当前请求完整 URL
   */
  static function current()
  {
    $base = self::baseURL();
    if (!$base) return "";

    $pathName = "";
    if (array_key_exists("REQUEST_URI", $_SERVER) && $_SERVER['REQUEST_URI']) {
      $pathName = $_SERVER['REQUEST_URI'];
    }

    return $base . $pathName;
  }
  /**
   * 从当前请求构造 URL 实例
   *
   * @return URL 已解析当前请求的实例
   */
  static function fromCurrent()
  {
    return new self(self::current());
  }

  /**
   * 归一化域名（小写 + 剥端口 + 去 IPv6 方括号）
   *
   * Host 头按 RFC 不区分大小写，且可能携带端口（api.example.com:8080）或
   * IPv6 方括号形式（[::1]:8080）。域名路由在注册与匹配两侧都经此法归一，
   * 保证域名组注册的小写无端口形式能命中任意大小写/带端口的请求 Host。
   *
   * @param string $domain 原始域名
   * @return string 归一化后的域名
   */
  static function normalizeDomain($domain)
  {
    $domain = strtolower(trim($domain));
    if (strpos($domain, "[") === 0) {
      $close = strpos($domain, "]");
      if ($close !== false) {
        $domain = substr($domain, 1, $close - 1);
      }
    } elseif (substr_count($domain, ":") === 1) {
      $colon = strpos($domain, ":");
      if (ctype_digit(substr($domain, $colon + 1))) {
        $domain = substr($domain, 0, $colon);
      }
    }
    return $domain;
  }
}
