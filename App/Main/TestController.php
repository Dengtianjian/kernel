<?php

namespace kernel\App\Main;

use kernel\Foundation\Controller;
use kernel\Foundation\Output;
use kernel\Foundation\Response;
use kernel\Foundation\Validator;

class TestController extends Controller
{
  public $query = [
    "username"
  ];
  public $rules = [
    "username" => [
      "type" => "integer",
      "message" => "用户名必须是数字类型"
    ]
  ];
  public function get()
  {
    Response::null(203);
  }
}
