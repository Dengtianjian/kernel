<?php

namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Output;

class RequestBody extends RequestData
{
  protected $body = [];
  public function __construct($dataConversion = null, $validator = null)
  {
    $this->dataConversion = $dataConversion;
    $this->validator = $validator;

    $input = \file_get_contents("php://input");
    $data = [];
    if ($input) {
      $data = json_decode($input, true);
      if ($data === null) {
        $data = simplexml_load_string($input);
        if ($data === false) {
          $data = [];
        } else {
          $data = json_encode($data);
          $data = json_decode($data, true);
        }
      }
    }

    $this->data = \array_merge($data, $_POST);
  }
}
