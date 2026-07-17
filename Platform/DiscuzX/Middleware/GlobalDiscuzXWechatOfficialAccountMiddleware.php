<?php

namespace kernel\Platform\DiscuzX\Middleware;

use kernel\Middleware\GlobalWechatOfficialAccountMiddleware;
use kernel\Platform\DiscuzX\Model\DiscuzXAccessTokenModel;

class GlobalDiscuzXWechatOfficialAccountMiddleware extends GlobalWechatOfficialAccountMiddleware
{
  public function __construct($request, $controller)
  {
    parent::__construct($request, $controller);
    $this->accessTokenModel = DiscuzXAccessTokenModel::class;
  }
}
