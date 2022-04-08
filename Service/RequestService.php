<?php

namespace kernel\Service;

use kernel\Foundation\Request;
use kernel\Foundation\Service;

class RequestService extends Service
{
  static function request(): Request
  {
    return $GLOBALS['App']->request;
  }
}
