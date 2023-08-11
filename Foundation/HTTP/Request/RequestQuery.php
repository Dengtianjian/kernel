<?php

namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Output;

class RequestQuery extends RequestData
{
  public function __construct($dataConversion = null, $validator = null)
  {
    $this->dataConversion = $dataConversion;
    $this->validator = $validator;

    foreach ($_GET as $key => $value) {
      if (is_string($value)) {
        $this->data[$key] = $value;
      }
    }
  }
  /**
   * 获取某个键的值
   *
   * @param string $key 键名
   * @return string
   */
  public function get($key)
  {
    return parent::get($key);
  }
}
