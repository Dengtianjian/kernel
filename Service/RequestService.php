<?php

namespace gstudio_kernel\Service;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Foundation\Request;
use gstudio_kernel\Foundation\Service;

class RequestService extends Service
{
  static function request()
  {
    return $GLOBALS['App']->request;
  }
}
