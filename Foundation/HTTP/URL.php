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
  /**
   * 解析URL
   *
   * @param string $URL URL地址
   * @return array
   */
  static function parseURL($URL)
  {
    $ParsedURL = parse_url($URL);

    $origin = explode("?", explode("&", $URL)[0])[0];
    if (substr($origin,  strlen($origin) - 1)) {
      $origin = substr($origin, 0, strlen($origin) - 1);
    }

    return [
      "protocol" => $ParsedURL['scheme'],
      "host" => $ParsedURL['host'],
      "port" => $ParsedURL['port'],
      "user" => $ParsedURL['user'],
      "password" => $ParsedURL['pass'],
      "pathName" => $ParsedURL['path'],
      "queryString" => $ParsedURL['query'],
      "fragment" => $ParsedURL['fragment'],
      "origin" => $origin,
      "queryParams" => self::parseQueryString($ParsedURL['query'])
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
      list($key, $value) = explode("=", $item);
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
    if ($user || $password) {
      if ($user && $password) {
        $auth = "{$user}:{$password}@";
      } else if ($user) {
        $auth = "{$user}@";
      } else {
        $auth = "{$password}@";
      }
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
    $path = implode(DIRECTORY_SEPARATOR, array_map(function ($item) {
      // $lastText = $item[strlen($item) - 1];
      // if ($lastText === "/" || $lastText === "\\") {
      //   $item = substr($item, 0, strlen($item) - 1);
      // }
      // if ($item[0] === "/" || $item[0] === "\\") {
      //   $item = substr($item, 1, strlen($item));
      // }
      return $item;
    }, array_filter($paths, function ($item) {
      return !empty(trim($item));
    })));
    $path = str_replace([
      "//",
      "\\",
      "/",
      "\\\\"
    ], "/", $path);

    return $path;
  }
  /**
   * 字符串化URL所有参数，也就是把所有参数组合成一个URL
   *
   * @return string
   */
  public function toString()
  {
    return self::buildURL($this->host, $this->pathName, $this->queryParams, $this->fragment, $this->protocol, $this->port, $this->user, $this->password);
  }
  public function __toString()
  {
    return $this->toString();
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
      foreach ($value as $key => $item) {
        if (!is_numeric($key)) {
          $key = $item;
          $item = null;
        }
        $this->queryParam($item, $key);
      }
    } else {
      if (!$key) {
        $key = $value;
        $value = "";
      }
      $this->queryParams[$key] = $value;
    }

    return $this;
  }
}
