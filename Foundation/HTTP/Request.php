<?php

namespace kernel\Foundation\HTTP;

use kernel\Foundation\HTTP\Request\RequestBody;
use kernel\Foundation\HTTP\Request\RequestHeader;
use kernel\Foundation\HTTP\Request\RequestModelParams;
use kernel\Foundation\HTTP\Request\RequestPagination;
use kernel\Foundation\HTTP\Request\RequestParams;
use kernel\Foundation\HTTP\Request\RequestQuery;
use kernel\Foundation\Output;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

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
   * 分页
   *
   * @var RequestPagination
   */
  public $pagination = null;
  /**
   * URI参数
   *
   * @var RequestParams
   */
  public $params = null;
  /**
   * 用于模型查询的参数
   *
   * @var RequestModelParams
   */
  public $modelParams = null;

  /**
   * 请求方法
   *
   * @var string
   */
  public $method = "get";
  /**
   * 请求URI
   *
   * @var string
   */
  public $URI = null;
  /**
   * 当前匹配到的路由
   *
   * @var array
   */
  public $Route = null;

  public function __construct()
  {
    $this->query = new RequestQuery($this);
    $this->body = new RequestBody($this);
    $this->header = new RequestHeader($this);
    $this->pagination = new RequestPagination($this);
    $this->params = new RequestParams($this);
    $this->modelParams = new RequestModelParams($this);

    $this->getMethod();
    $this->getURI();
  }
  /**
   * 是否是AJAX异步请求
   *
   * @return bool
   */
  public function ajax()
  {
    if (F_APP_MODE === "development") {
      if ($this->query->has("x-ajax")) return 1;
      if ($this->body->has("x-ajax")) return 1;
      if ($this->params->has("x-ajax")) return 1;
    }

    if (array_key_exists("HTTP_X_REQUESTED_WITH",$_SERVER)) {
      if ($_SERVER['HTTP_X_REQUESTED_WITH'] === "XMLHttpRequest" || $_SERVER['HTTP_X_REQUESTED_WITH'] === "fetch") return true;
    }
    if ($this->header->has("x-ajax") || $this->header->has("X-Ajax")) return 1;
    if ($this->query->has("isAjax")) return 1;

    return 0;
  }
  /**
   * 是否是ASYNC请求。该类型请求是服务器自己通过CURL向自己发起的HTTP请求，头部会带有x-async标识
   *
   * @return void
   */
  public function async()
  {
    if (F_APP_MODE === "development") {
      if ($this->query->has("x-async")) return true;
      if ($this->body->has("x-async")) return true;
      if ($this->params->has("x-async")) return true;
    }

    if ($this->header->has("X-Async") || $this->header->has("x-async")) return true;

    return false;
  }
  /**
   * 获取请求方法
   *
   * @return void
   */
  private function getMethod()
  {
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : "get";
    if (F_APP_MODE === "development") {
      if ($this->query->has("_method")) $method = $this->query->get("_method");
      if ($this->params->has("_method")) $method = $this->params->get("_method");
    }
    if ($this->body->has("_method")) $method = $this->body->get("_method");
    $this->method = strtolower(addslashes($method));
  }
  /**
   * 获取请求URI
   *
   * @return void
   */
  private function getURI()
  {
    if ($this->query->has("uri")) {
      $this->URI = addslashes($this->query->get("uri"));
    } else {
      $this->URI = addslashes(substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], "?") ?: strlen($_SERVER['REQUEST_URI'])));
    }
  }
}
