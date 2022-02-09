<?php

namespace kernel\App\Main\System;

use kernel\Foundation\Request;
use kernel\Foundation\Controller;
use kernel\Foundation\Iuu;
use kernel\Foundation\Response;

class UpgradeController extends Controller
{
  public function data(Request $R)
  {
    set_time_limit(0); //* 不超时断开Http链接
    $key = $R->body("key");
    if (Iuu::verificationKey($key) === false) {
      Response::error(400, "WrongKey:400000", "密钥错误");
    }
    $IUU = new Iuu();
    return $IUU->upgrade();

    return $key;
  }
}
