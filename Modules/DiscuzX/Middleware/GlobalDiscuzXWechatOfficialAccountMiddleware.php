<?php

namespace kernel\Modules\DiscuzX\Middleware;

use kernel\Middleware\GlobalWechatOfficialAccountMiddleware;
use kernel\Modules\DiscuzX\Model\DiscuzXAccessTokenModel;

class GlobalDiscuzXWechatOfficialAccountMiddleware extends GlobalWechatOfficialAccountMiddleware
{
  public function __construct($request, $controller)
  {
    parent::__construct($request, $controller);
    $this->accessTokenModel = DiscuzXAccessTokenModel::class;
  }
}
