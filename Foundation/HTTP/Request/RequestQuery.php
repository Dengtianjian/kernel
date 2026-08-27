<?php

namespace kernel\Foundation\HTTP\Request;

class RequestQuery extends RequestData
{
  public function __construct($mutator = null, $validator = null)
  {
    $this->mutator = $mutator;
    $this->validator = $validator;

    foreach ($_GET as $key => $value) {
      // 保留字符串与数组型参数（如 ?tag[]=a&tag[]=b），其他类型忽略
      if (is_string($value) || is_array($value)) {
        $this->data[$key] = $value;
      }
    }
  }
}
