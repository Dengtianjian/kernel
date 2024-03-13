<?php

namespace kernel\Platform\DiscuzX;

use kernel\Foundation\HTTP\URL;

class DiscuzXURL extends URL
{
  public $pathName = "plugin.php";
  public $uri = null;
  public function __construct($URL = null)
  {
    parent::__construct($URL);

    if ($URL) {
      $ParsedURL = self::parseURL($URL);

      if (!$ParsedURL['pathName']) {
        $this->pathName = "plugin.php";
      }

      if (array_key_exists("uri", $ParsedURL['queryParams'])) {
        $this->uri = $ParsedURL['queryParams']['uri'];
      }
    }
  }

  /**
   * 构建URL
   *
   * @param string $host 主机信息
   * @param string $pathName 路径
   * @param string $uri URI
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
    $uri = null,
    $queryParams = [],
    $fragment = null,
    $protocol = "https",
    $port = null,
    $user = null,
    $password = null
  ) {

    if (!is_null($uri)) {
      $queryParams['uri'] = $uri;
    }

    return parent::buildURL($host, $pathName, $queryParams, $fragment, $protocol, $port, $user, $password);
  }
  static function buildPluginURL($PluginId = F_APP_ID, $URI = null, $BaseURL = F_BASE_URL)
  {
    $U = new self($BaseURL);
    $U->queryParam("id", $PluginId);
    if ($URI) {
      $U->queryParam("uri", $URI);
    }

    return $U->toString();
  }
  public function toString()
  {
    return self::buildURL($this->host, $this->pathName, $this->uri, $this->queryParams, $this->fragment, $this->protocol, $this->port, $this->user, $this->password);
  }
}
