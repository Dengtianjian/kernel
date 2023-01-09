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
}
