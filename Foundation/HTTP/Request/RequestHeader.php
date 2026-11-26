<?php

namespace kernel\Foundation\HTTP\Request;

class RequestHeader extends RequestData
{
  public function __construct()
  {
    $headers = [];
    if (\function_exists("getallheaders")) {
      $headers = \getallheaders();
    } else {
      foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
          $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
      }
    }

    $this->data = $headers;
  }

  /**
   * 按名称取请求头（大小写不敏感）
   *
   * `getallheaders()` 等 SAPI 返回的头名称大小写不统一（如 `X-Csrf-Token`），
   * 而 HTTP 头本身大小写不敏感，这里统一按不区分大小写匹配。
   *
   * @param string $key
   * @param mixed $default
   * @return mixed
   */
  public function get($key, $default = null)
  {
    if (!is_array($this->data)) {
      return $default;
    }

    foreach ($this->data as $name => $value) {
      if (strcasecmp((string) $name, (string) $key) === 0) {
        return $value;
      }
    }

    return $default;
  }

  /**
   * 是否存在某个请求头（大小写不敏感）
   *
   * @param string $key
   * @return boolean
   */
  public function has($key)
  {
    if (!is_array($this->data)) {
      return false;
    }

    foreach ($this->data as $name => $value) {
      if (strcasecmp((string) $name, (string) $key) === 0) {
        return true;
      }
    }

    return false;
  }
}
