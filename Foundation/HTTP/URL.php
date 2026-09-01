<?php

namespace kernel\Foundation\HTTP;

/**
 * URL 统一资源定位器类
 *
 * 提供 URL 的解析、构建、读写与规范化能力：
 * - 通过 `parseURL()` 将字符串解析为各组成部分，并在构造时填充到实例属性；
 * - 通过 `buildURL()` / `buildQuery()` 反向拼装 URL 与查询字符串；
 * - 实例支持链式修改协议、主机、路径、端口、参数等，再由 `toString()` 还原为完整 URL；
 * - 提供 `current()` / `fromCurrent()` 读取并解析当前请求 URL；
 * - 提供 `normalizeDomain()` 对域名做路由级归一化（小写、去端口、去 IPv6 方括号）。
 *
 * 所有组成部分在解析缺失时统一置为 `null`（而非空字符串），避免下标访问警告。
 */
class URL
{
  /** @var string|null 原始传入的 URL 字符串 */
  protected $URL = null;
  /** @var string|null 主机名，如 `example.com` */
  public $host = null;
  /** @var string|null 源站（origin）：`scheme://host[:port]`，仅当 scheme 与 host 同时存在时计算 */
  public $origin = null;
  /** @var int|null 端口号，缺省为 null（表示使用协议默认端口） */
  public $port = null;
  /** @var string|null 用户身份信息中的用户名（URL 的 userinfo 段） */
  public $user = null;
  /** @var string|null 用户身份信息中的密码（URL 的 userinfo 段） */
  public $password = null;
  /** @var string|null 路径部分，如 `/path/to/page` */
  public $pathName = null;
  /** @var string|null 协议（scheme），如 `http` / `https` */
  public $protocol = null;
  /** @var string|null 原始查询字符串（不含前导 `?`），如 `a=1&b=2` */
  public $queryString = null;
  /** @var array 解析后的查询参数键值对，如 `["a" => "1", "b" => "2"]` */
  public $queryParams = [];
  /** @var string|null 片段（fragment），即 `#` 之后的 hash 部分，不含前导 `#` */
  public $fragment = null;
  /**
   * 构建 URL 类实例
   *
   * 解析传入的 URL 字符串，并将各组成部分写入对应的公开属性。
   * 传入 `null` 时所有属性保持默认值（多为 `null`），可后续通过 setter 逐项赋值再 `toString()`。
   *
   * @param string|null $URL 待解析的 URL 字符串，为空则仅初始化属性
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
   * 解析 URL 为结构化数组
   *
   * 使用 PHP 内置 `parse_url()` 拆解 URL，并对缺失的组成部分统一返回 `null`。
   * `origin` 字段按标准语义计算为 `scheme://host[:port]`（仅当 scheme 与 host 同时存在时）。
   * `query` 部分会进一步经 `parseQueryString()` 解析为关联数组。
   *
   * @param string $URL URL 地址
   * @return array 含 protocol/host/port/user/password/pathName/queryString/fragment/origin/queryParams 的结构
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
   * 解析查询字符串为键值对数组
   *
   * 以 `&` 分割参数，每段再以首个 `=` 拆分为键与值。
   * 无 `=` 的参数（如 `?a&b`）其值退化为空字符串；键与值均经 `urldecode` + `rawurldecode` 双重解码。
   * 空字符串或非字符串入参直接返回空数组。
   *
   * @param string|null $queryString 查询字符串（不含前导 `?`）
   * @return array 形如 `["a" => "1", "b" => "2"]` 的关联数组
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
   * 由关联数组构建查询字符串
   *
   * 遍历参数拼装 `key=value`，以 `&` 连接。当键为数值时退化为「裸参数」：
   * 以值作为键名、值置空（即 `value=`），仅输出键名本身。
   * `$encode` 为 true 时对键与值调用 `rawurlencode`。
   *
   * @param array $queryParams 请求参数键值对
   * @param boolean $encode 是否对键值进行编码（默认 true）
   * @return string 构建后的查询字符串（不含前导 `?`），无参数时返回空串
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
   * 由各部分拼装完整 URL
   *
   * 各片段空值时会自动省略对应段（如 `$port` 为空则不拼 `:port`）。
   * userinfo 按 `user:pass@` 形式拼接，仅存在用户名时也会附加 `@`。
   * 查询参数经 `buildQuery()` 生成，且自动加前导 `?`（仅当非空）。
   *
   * @param string $host 主机名
   * @param string $pathName 路径（会自动补前导 `/`）
   * @param array $queryParams 查询参数键值对
   * @param string|null $fragment hash 片段（自动补前导 `#`）
   * @param string $protocol 协议（默认 `https`）
   * @param int|null $port 端口（自动补前导 `:`）
   * @param string|null $user 用户名（userinfo 段）
   * @param string|null $password 密码（userinfo 段）
   * @return string 构建后的完整 URL
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
   * 组合多个路径段为一个归一化路径
   *
   * 过滤空段，并去除每段首尾的斜杠/反斜杠，再以单斜杠拼接，天然避免段间重复斜杠。
   * 最后用正则仅折叠「非协议冒号后」的连续斜杠，避免误伤 `https://host` 中的合法双斜杠。
   *
   * @param string[] ...$paths 路径元素（可变参数）
   * @return string 拼接并归一化后的路径
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
   * @return $this
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
   * @return $this
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
   * @return $this
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
   * @return $this
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
   * @return $this
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
   * 设置（写入）查询参数，支持两种调用形态：
   *
   * 1. 批量数组：`queryParam(['a' => '1', 'b'])`，以键值对写入；
   *    数值键（如 `'b'`）退化为裸参数，键名即参数名、值置空。
   * 2. 单参数：
   *    - 同时传 `$key` 与 `$value`：写入 `$queryParams[$key] = $value`；
   *    - 仅传 `$value`（或 `$value` 为真且 `$key` 为空）：把 `$value` 当作裸参数键名（解码后）写入，值置空。
   *
   * 注意：当 `$value` 为空串且未提供 `$key` 时不会写入（避免写入空键）。
   * 所有写入均返回 `$this`，可链式调用。
   *
   * @param string|array $value 参数值或批量参数数组
   * @param string|null $key 参数名（单参数形态下）
   * @return $this
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
   * @return $this
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
   * @return $this
   */
  public function clearQueryParams()
  {
    $this->queryParams = [];
    return $this;
  }
  /**
   * 获取当前请求的完整 URL
   *
   * 基于 `$_SERVER` 推导：先经 `baseURL()` 取得 `scheme://host`，再拼接 `REQUEST_URI`（含路径、query 与 fragment）。
   * 在 CLI 或非 Web 上下文（`REQUEST_SCHEME`/`HTTP_HOST` 缺失）下 `baseURL()` 返回空串，此时本方法返回空串。
   *
   * @return string 当前请求完整 URL，无法推导时返回空串
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
