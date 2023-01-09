<?php

namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Output;

class RequestQuery extends RequestData
{
  public function __construct()
  {
    foreach ($_GET as $key => $value) {
      if (is_string($value)) {
        $this->data[$key] = $value;
      }
    }
  }
}
