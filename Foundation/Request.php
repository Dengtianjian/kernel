<?php

namespace kernel\Foundation;

if (!defined("F_KERNEL")) {
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
  public $uri = "";
  public $router = null;
  public $method = "";
  public function __construct()
  {
    $this->serializationBody();
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

    $this->method = $_SERVER['REQUEST_METHOD'];
    if (isset($_REQUEST['_method'])) {
      $this->method = $_REQUEST['_method'];
    }
    $this->method = strtoupper($this->method);

    $this->uri = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], "?") ?: strlen($_SERVER['REQUEST_URI']));
  }
  private function serializationBody()
  {
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
        return \getallheaders()[$key];
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
      return $this->headers[$key];
    }
    return $this->headers;
  }
  public function ajax()
  {
    if (isset($_GET['isAjax'])) {
      return true;
    }
    return $this->headers("X-Ajax");
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
      array_push($paramsKeys, $key);
      return $this->getArrayData($this->paramsData, $keys);
    }
    return $this->getArrayData($this->paramsData, $key);
  }
  public function pagination(string $key = null)
  {
    if ($key) {
      return $this->paginationParams[$key];
    }
    return $this->paginationParams;
  }
}
