<?php

namespace kernel\Service;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Output;
use kernel\Foundation\Request;
use kernel\Foundation\Service;

class RequestService extends Service
{
  static function request()
  {
    return $GLOBALS['App']->request;
  }
}
