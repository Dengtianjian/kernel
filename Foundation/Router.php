<?php

namespace gstudio_kernel\Foundation;

use gstudio_kernel\Foundation\Router\Router as RouterRouter;
use gstudio_kernel\Foundation\Router\RouterBase;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

//* 兼容别的插件，后续移除~
class Router extends RouterRouter
{
}
