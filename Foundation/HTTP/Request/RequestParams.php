<?php

namespace kernel\Foundation\HTTP\Request;

class RequestParams extends RequestData
{
  private $params = [];
  public function set($params)
  {
    $this->data = $params;
  }
}
