<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Controller
{
  public $query = [];
  public $body = [];
  public $serialization = [];
  public $rules = [];
  function __construct(Request $R)
  {
    $this->queryInit($R->query());
    $this->body = $this->recursionGetBody($this->body, $R->body());
    if (count($this->rules) > 0) {
      $V = new Validator($this->rules, Arr::merge($this->query, $this->body));
      $V->validate();
    }

    if (count($R->pipes)) {
      Response::intercept(function ($statusCode, $code, $data) use ($R) {
        $this->pipeFill($R->pipes, $data);
      }, "success");
    }
  }
  public function __get($name)
  {
    return $this->$name;
  }
  private function queryInit($requestQuery)
  {
    $needQuery = $this->query;
    $query = [];
    foreach ($needQuery as $key => $type) {
      if (is_numeric($key)) {
        $query[$type] = $requestQuery[$type] ?? null;
      } else {
        $query[$key] = $requestQuery[$key] ?? null;
        if (trim($query[$key]) === "") {
          $query[$key] = null;
        }
        if ($query[$key] !== null) {
          settype($query[$key], $type);
          if (gettype($query[$key]) === "string") {
            $query[$key] = addslashes($query[$key]);
          }
        }
      }
    }
    $this->query = $query;
  }
  private function recursionGetBody($needBody, $requestBody)
  {
    $body = [];
    foreach ($needBody as $key => $type) {
      if (is_numeric($key)) {
        if (isset($requestBody[$type])) {
          $body[$type] = $this->convertDataType($requestBody[$type], "any");
        } else {
          $body[$type] = null;
        }
      } else {
        if (is_array($needBody[$key])) {
          if (is_array($requestBody[$key])) {
            $body[$key] = $this->recursionGetBody($needBody[$key], $requestBody[$key]);
          } else {
            $body[$key] = null;
          }
        } else {
          if (isset($requestBody[$key])) {
            $body[$key] = $this->convertDataType($requestBody[$key], $type);
          } else {
            $body[$key] = null;
          }
        }
      }
    }
    return $body;
  }
  private function convertDataType($data, string $type)
  {
    if ($type !== "array" && $type !== "object" && $type !== null && !is_array($data) && !is_object($data)) {
      $data = trim($data);
    }
    if ($type === "any") {
      if (is_numeric($data)) {
        if (strpos(strval($data), ".") === false) {
          $data = intval($data);
        } else {
          $data = doubleval($data);
        }
      } else if (is_string($data)) {
        $data = addslashes($data);
      }
    } else {
      settype($data, $type);
      if ($type === "string") {
        $data = addslashes($data);
      }
    }

    return $data;
  }
  protected function pipeFill(array $pipes, mixed $data): void
  {
    $result = null;
    foreach ($pipes as $pipeName) {
      if (!method_exists($this, $pipeName)) {
        continue;
      }
      $result = call_user_func([$this, $pipeName], $data);
    }
    Response::add([
      "data" => $result
    ]);
  }
}
