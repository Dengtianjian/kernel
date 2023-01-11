<?php

namespace gstudio_kernel\Foundation;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class Request
{
  private $body = [];
  private $headers = [];
  private $paramsData = [];
  private $paginationParams = [
    "page" => 1,
  ];
  public $pipes = [];
  public $uri = "";
  public $router = null;
  public $method = "";
  public $client = "user";
  public function __construct()
  {
    $this->serializationBody();

    //* 管道参数处理
    if (isset($_GET["_pipes"])) {
      $this->pipes = array_merge($this->pipes, explode(",", $_GET['_pipes']));
      unset($_GET["_pipes"]);
    }
    if (isset($this->body["_pipes"])) {
      $this->pipes = array_merge($this->pipes, $this->body['_pipes']);
      unset($this->body["_pipes"]);
      unset($_POST["_pipes"]);
    }
    unset($_REQUEST["_pipes"]);
    $this->pipes = array_map(function ($item) {
      return addslashes($item);
    }, $this->pipes);

    //* 分页参数
    $this->paginationParams = [
      "page" => isset($_REQUEST["page"]) ? intval($_REQUEST["page"]) : 1,
      "limit" => 10,
      "skip" => isset($_REQUEST["skip"]) ? intval($_REQUEST["skip"]) : null
    ];

    if (isset($_REQUEST["limit"])) {
      $this->paginationParams['limit'] = intval($_REQUEST["limit"]);
    } else if (isset($_REQUEST["perPage"])) {
      $this->paginationParams['limit'] = intval($_REQUEST["perPage"]);
    }

    //* 请求方式
    $this->method = $_SERVER['REQUEST_METHOD'];
    if (isset($_REQUEST['_method'])) {
      $this->method = $_REQUEST['_method'];
    }
    $this->method = strtoupper($this->method);

    //* 请求的URI
    $this->uri = addslashes($_GET['uri']);

    //* 客户端标识符
    $this->client = $this->headers("X-Client");
  }
  private function serializationBody()
  {
    //* 请求体处理
    $body = \file_get_contents("php://input");
    if ($body) {
      $body = \json_decode($body, true);
      if ($body === null) {
        $body = [];
      }
    } else {
      $body = [];
    }

    $this->body = \array_merge($body, $_POST);
  }
  private function getArrayData($arr, $keys)
  {
    if (\is_string($keys)) {
      if (!isset($arr[$keys])) return null;
      return $arr[$keys];
    } else if (\is_array($keys)) {
      $returns = [];
      foreach ($arr as $key => $item) {
        if (\in_array($key, $keys)) {
          $returns[$key] = $item;
        }
      }
      return $returns;
    }

    return $arr;
  }
  public function body($paramsKey = null, ...$paramsKeys)
  {
    if (empty($paramsKey) || !$paramsKey) return $this->body;
    if (count($paramsKeys) > 0) {
      array_push($paramsKeys, $paramsKey);
      return $this->getArrayData($this->body, $paramsKeys);
    }
    return $this->getArrayData($this->body, $paramsKey);
  }
  public function query($key = null)
  {
    return $this->getArrayData($_GET, $key);
  }
  public function remove($key)
  {
    unset($this->body[$key]);
  }
  public function headers($key = null)
  {
    if (\function_exists("getallheaders")) {
      if (isset($key)) {
        if (isset(\getallheaders()[$key])) {
          return \getallheaders()[$key];
        }
        return null;
      }
      return \getallheaders();
    }

    if (empty($this->headers)) {
      $headers = [];
      foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
          $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
      }
      $this->headers = $headers;
    }
    if ($key) {
      if (!isset($this->headers[$key])) {
        return null;
      }
      return $this->headers[$key];
    }
    return $this->headers;
  }
  /**
   * 是否是AJAX异步请求，也就是非页面渲染
   *
   * @return bool|string
   */
  public function ajax()
  {
    if (isset($_GET['isAjax'])) {
      return true;
    }
    return $this->headers("X-Ajax");
  }
  /**
   * 是否是内部异步请求
   *
   * @return bool|string
   */
  public function async()
  {
    if (isset($_GET['isAsync'])) {
      return true;
    }
    return $this->headers("X-Async");
  }
  public function setParams($params = [])
  {
    $params = $params ?: [];
    $this->paramsData = array_merge($this->paramsData, $params);
    return $this->paramsData;
  }
  public function params($key = null, ...$keys)
  {
    if (empty($key) || !$key) return $this->paramsData;
    if (count($keys) > 0) {
      array_push($keys, $key);
      return $this->getArrayData($this->paramsData, $keys);
    }
    return $this->getArrayData($this->paramsData, $key);
  }
  public function pagination($key = null)
  {
    if ($key) {
      return $this->paginationParams[$key];
    }
    return $this->paginationParams;
  }
  public function set($uri,  $method)
  {
    $this->uri = $uri;
    $this->method = $method;
  }
}
